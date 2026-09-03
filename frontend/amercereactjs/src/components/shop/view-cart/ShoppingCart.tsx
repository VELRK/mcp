import { Link } from "react-router-dom";
import { apiImageUrl } from "@/hooks/useApi";
import { useMemo, useState, memo, useEffect } from "react";
import { useStore, type CartProduct } from "@/context/store";
import { useAuthStore } from "@/store/authStore";
import type { ProductId } from "@/context/store";
import { formatPrice } from "@/utils/formatPrice";
import { promoAPI, siteSettingsAPI, cartAPI } from "@/services/api";
import { useModalStore } from "@/store/modalStore";
import { loadStoredPromo, saveStoredPromo } from "@/utils/promoStorage";
import { removeLineFromCart } from "@/utils/cartSync";

export default function ShoppingCart() {
  const cartProducts = useStore((s) => s.cartProducts);
  const updateQuantity = useStore((s) => s.updateQuantity);
  const totalPrice = useStore((s) => s.totalPrice);
  const { isLoggedIn } = useAuthStore();

  /* ── Promo code ── */
  const [promoInput, setPromoInput] = useState("");
  const [appliedCode, setAppliedCode] = useState("");
  const [promoDiscount, setPromoDiscount] = useState(0);
  const [promoError, setPromoError] = useState("");
  const [promoLoading, setPromoLoading] = useState(false);

  /* ── Site Settings ── */
  const [shippingCharge, setShippingCharge] = useState(50);
  const [freeShippingAbove] = useState(100);

  useMemo(() => {
    siteSettingsAPI.get().then(res => {
      if (res.data.success && res.data.data) {
        const s = res.data.data;
        if (typeof s.shipping_charge === 'number') setShippingCharge(s.shipping_charge);
        // if (typeof s.free_shipping_above === 'number') setFreeShippingAbove(s.free_shipping_above);
      }
    }).catch(err => console.error("Failed to load settings", err));
  }, []);

  useEffect(() => {
    if (!isLoggedIn || totalPrice <= 0) return;
    const stored = loadStoredPromo();
    if (stored) {
      setAppliedCode(stored.code);
      setPromoDiscount(stored.discount);
    }
  }, [isLoggedIn, totalPrice]);

  const handleApplyPromo = async () => {
    const code = promoInput.trim().toUpperCase();
    if (!code) return;
    if (!isLoggedIn) { setPromoError("Please login to apply a promo code."); return; }
    setPromoLoading(true); setPromoError("");
    try {
      const res = await promoAPI.apply({ code, order_amount: totalPrice });
      const r = res.data as { success?: boolean; data?: { discount: number; code: string }; message?: string };
      if (r.success && r.data) {
        setAppliedCode(r.data.code);
        setPromoDiscount(r.data.discount);
        setPromoInput("");
        saveStoredPromo({ code: r.data.code, discount: r.data.discount });
      } else {
        setPromoError(r.message ?? "Invalid promo code.");
      }
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      setPromoError(msg ?? "Invalid or expired promo code.");
    } finally {
      setPromoLoading(false);
    }
  };

  const removePromo = () => {
    setAppliedCode(""); setPromoDiscount(0); setPromoError("");
    saveStoredPromo(null);
  };

  const discount = promoDiscount;
  const shippingCost = totalPrice <= 0 ? 0 : (totalPrice >= freeShippingAbove ? 0 : shippingCharge);
  const subtotalAfterPromo = Math.max(0, totalPrice - discount);
  const billTotal = subtotalAfterPromo + shippingCost;
  const amountDue = billTotal;
  const amountToFreeship = Math.max(0, freeShippingAbove - totalPrice);

  const removeLine = (id: ProductId, variantId?: number, index?: number) => {
    removeLineFromCart(id, variantId, index);
  };

  const setQty = (id: ProductId, qty: number, variantId?: number, index?: number) => {
    if (qty < 1) { removeLine(id, variantId, index); return; }
    updateQuantity(id, qty, variantId);
    cartAPI.update({ product_id: Number(id), quantity: qty, ...(variantId != null ? { variant_id: variantId } : {}) }).catch(() => { });
  };

  return (
    <>
      <style>{`
        .classic-cart-section {
          padding: 48px 0 64px;
          background-color: #fafafa;
          min-height: 60vh;
          font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
        }
        .classic-cart-title {
          font-size: 28px;
          font-weight: 800;
          color: #1a202c;
          letter-spacing: -0.5px;
          margin-bottom: 4px;
        }
        .classic-cart-subtitle {
          font-size: 14px;
          color: #718096;
          margin-bottom: 32px;
        }
        .classic-cart-table-wrap {
          background: #ffffff;
          border: 1px solid #e8ecf0;
          border-radius: 12px;
          overflow: hidden;
          box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .classic-cart-table {
          width: 100%;
          border-collapse: collapse;
        }
        .classic-cart-table thead tr {
          background-color: #f7f8fa;
          border-bottom: 2px solid #e8ecf0;
        }
        .classic-cart-table thead th {
          padding: 14px 20px;
          font-size: 11px;
          font-weight: 700;
          color: #718096;
          text-transform: uppercase;
          letter-spacing: 0.8px;
          text-align: left;
        }
        .classic-cart-table thead th.col-total {
          text-align: right;
        }
        .classic-cart-table thead th.col-qty {
          text-align: center;
        }
        .classic-cart-table tbody tr {
          border-bottom: 1px solid #f0f2f5;
          transition: background-color 0.15s ease;
        }
        .classic-cart-table tbody tr:last-child {
          border-bottom: none;
        }
        .classic-cart-table tbody tr:hover {
          background-color: #fafbfc;
        }
        .classic-cart-table td {
          padding: 20px;
          vertical-align: middle;
        }
        .cart-product-cell {
          display: flex;
          align-items: center;
          gap: 16px;
        }
        .cart-product-img {
          width: 80px;
          height: 88px;
          flex-shrink: 0;
          border-radius: 8px;
          overflow: hidden;
          border: 1px solid #e8ecf0;
          background-color: #f7f8fa;
        }
        .cart-product-img img {
          width: 100%;
          height: 100%;
          object-fit: cover;
          display: block;
        }
        .cart-product-info .product-name {
          font-size: 14px;
          font-weight: 600;
          color: #1a202c;
          text-decoration: none;
          line-height: 1.4;
          display: block;
          margin-bottom: 4px;
          transition: color 0.15s;
        }
        .cart-product-info .product-name:hover {
          color: #3ec1bc;
        }
        .cart-product-info .product-meta {
          font-size: 12px;
          color: #a0aec0;
          margin-bottom: 2px;
        }
        .cart-product-info .product-meta span {
          font-weight: 600;
          color: #718096;
        }
        .cart-remove-btn {
          font-size: 12px;
          color: #fc8181;
          background: none;
          border: none;
          cursor: pointer;
          padding: 0;
          font-weight: 600;
          margin-top: 6px;
          display: inline-flex;
          align-items: center;
          gap: 4px;
          transition: color 0.15s;
          text-decoration: underline;
          text-underline-offset: 2px;
        }
        .cart-remove-btn:hover {
          color: #e53e3e;
        }
        .cart-price-cell {
          font-size: 14px;
          font-weight: 600;
          color: #2d3748;
        }
        .cart-qty-cell {
          text-align: center;
        }
        .qty-stepper {
          display: inline-flex;
          align-items: center;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          overflow: hidden;
          background: #fff;
        }
        .qty-stepper button {
          background: none;
          border: none;
          padding: 8px 12px;
          cursor: pointer;
          font-size: 16px;
          color: #4a5568;
          font-weight: 500;
          line-height: 1;
          transition: background 0.15s;
        }
        .qty-stepper button:hover {
          background-color: #f7fafc;
        }
        .qty-stepper .qty-val {
          font-size: 14px;
          font-weight: 700;
          color: #1a202c;
          min-width: 32px;
          text-align: center;
          border-left: 1px solid #e2e8f0;
          border-right: 1px solid #e2e8f0;
          padding: 8px 4px;
          line-height: 1;
        }
        .cart-total-cell {
          text-align: right;
          font-size: 15px;
          font-weight: 700;
          color: #1a202c;
        }

        /* Promo */
        .classic-promo-wrap {
          background: #ffffff;
          border: 1px solid #e8ecf0;
          border-radius: 12px;
          padding: 20px;
          margin-top: 16px;
          box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .classic-promo-wrap label {
          font-size: 12px;
          font-weight: 700;
          color: #4a5568;
          text-transform: uppercase;
          letter-spacing: 0.6px;
          display: block;
          margin-bottom: 10px;
        }
        .promo-input-row {
          display: flex;
          gap: 10px;
        }
        .promo-input-row input {
          flex: 1;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          padding: 10px 14px;
          font-size: 13px;
          outline: none;
          color: #2d3748;
          transition: border-color 0.15s;
          background: #fafafa;
        }
        .promo-input-row input:focus {
          border-color: #3ec1bc;
          background: #fff;
        }
        .promo-apply-btn {
          background-color: #3ec1bc;
          color: white;
          border: none;
          border-radius: 8px;
          padding: 10px 18px;
          font-size: 13px;
          font-weight: 700;
          cursor: pointer;
          white-space: nowrap;
          transition: background-color 0.15s;
        }
        .promo-apply-btn:hover { background-color: #2da8a3; }
        .promo-apply-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .promo-applied-row {
          display: flex;
          align-items: center;
          justify-content: space-between;
          background: #f0fdf4;
          border: 1px solid #bbf7d0;
          border-radius: 8px;
          padding: 12px 14px;
        }
        .promo-applied-text { font-size: 13px; color: #166534; font-weight: 600; }
        .promo-remove-btn {
          font-size: 12px;
          color: #dc2626;
          background: none;
          border: none;
          cursor: pointer;
          font-weight: 700;
        }
        .promo-error { font-size: 12px; color: #dc2626; margin-top: 8px; }

        /* Order Summary Sidebar */
        .classic-summary-card {
          background: #ffffff;
          border: 1px solid #e8ecf0;
          border-radius: 12px;
          overflow: hidden;
          box-shadow: 0 1px 4px rgba(0,0,0,0.05);
          position: sticky;
          top: 100px;
        }
        .summary-card-header {
          background: #f7f8fa;
          padding: 16px 24px;
          border-bottom: 1px solid #e8ecf0;
        }
        .summary-card-header h5 {
          font-size: 13px;
          font-weight: 700;
          color: #4a5568;
          text-transform: uppercase;
          letter-spacing: 0.8px;
          margin: 0;
        }
        .summary-card-body {
          padding: 20px 24px;
        }
        .summary-line {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 12px;
          font-size: 14px;
        }
        .summary-line .label { color: #718096; font-weight: 500; }
        .summary-line .value { font-weight: 600; color: #2d3748; }
        .summary-line .value.free { color: #38a169; }
        .summary-line .value.discount { color: #38a169; }
        .summary-freeship-note {
          font-size: 11px;
          color: #a0aec0;
          margin-bottom: 12px;
          padding: 8px 12px;
          background: #f7f8fa;
          border-radius: 6px;
          line-height: 1.5;
        }
        .summary-divider {
          height: 1px;
          background: #e8ecf0;
          margin: 16px 0;
        }
        .summary-total-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
        }
        .summary-total-label {
          font-size: 16px;
          font-weight: 700;
          color: #1a202c;
        }
        .summary-total-value {
          font-size: 22px;
          font-weight: 800;
          color: #3ec1bc;
          letter-spacing: -0.5px;
        }
        .checkout-action-btn {
          display: block;
          width: 100%;
          background-color: #3ec1bc;
          color: #ffffff;
          text-align: center;
          padding: 15px;
          border-radius: 10px;
          border: none;
          font-size: 14px;
          font-weight: 700;
          text-transform: uppercase;
          letter-spacing: 0.5px;
          cursor: pointer;
          text-decoration: none;
          transition: background-color 0.2s ease;
          margin-bottom: 12px;
        }
        .checkout-action-btn:hover { background-color: #2da8a3; color: #fff; }
        .continue-shopping-link {
          display: block;
          text-align: center;
          font-size: 13px;
          font-weight: 600;
          color: #718096;
          text-decoration: none;
          padding: 10px;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          transition: all 0.15s;
        }
        .continue-shopping-link:hover {
          background: #f7f8fa;
          color: #4a5568;
        }
        .secure-badge {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 6px;
          font-size: 11px;
          color: #a0aec0;
          margin-top: 14px;
        }

        /* Free shipping progress banner on cart page */
        .cart-freeship-banner {
          background: #ffffff;
          border: 1px solid #e8ecf0;
          border-radius: 12px;
          padding: 16px 20px;
          margin-bottom: 24px;
          box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }
        .freeship-banner-info {
          font-size: 13px;
          color: #2d3748;
          margin-bottom: 8px;
        }
        .freeship-progress-track {
          height: 8px;
          background: #edf2f7;
          border-radius: 4px;
          overflow: hidden;
        }
        .freeship-progress-fill {
          height: 100%;
          border-radius: 4px;
          transition: width 0.3s ease, background-color 0.3s ease;
        }

        /* Mobile Sticky Bottom Bar */
        .mobile-cart-sticky-bar {
          display: none;
        }
        @media (max-width: 991px) {
          .mobile-cart-sticky-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 990;
            background: #ffffff;
            padding: 12px 16px max(12px, env(safe-area-inset-bottom, 12px));
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.12);
          }
          .mobile-bar-info {
            display: flex;
            flex-direction: column;
          }
          .mobile-bar-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
          }
          .mobile-bar-total {
            font-size: 19px;
            font-weight: 800;
            color: #3ec1bc;
            letter-spacing: -0.3px;
          }
          .mobile-bar-checkout-btn {
            background-color: #3ec1bc;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(62, 193, 188, 0.35);
          }
          .classic-cart-section {
            padding-bottom: 100px !important;
          }
        }

        /* Responsive Mobile Cart Table */
        @media (max-width: 767px) {
          .classic-cart-table thead {
            display: none;
          }
          .classic-cart-table, 
          .classic-cart-table tbody, 
          .classic-cart-table tr, 
          .classic-cart-table td {
            display: block;
            width: 100%;
          }
          .classic-cart-table tbody tr {
            padding: 16px;
            margin-bottom: 14px;
            background: #ffffff;
            border: 1px solid #e8ecf0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
          }
          .classic-cart-table td {
            padding: 8px 0;
            border: none;
          }
          .cart-product-cell {
            margin-bottom: 8px;
          }
          .cart-price-cell {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-top: 1px dashed #edf2f7;
            font-size: 14px;
          }
          .cart-price-cell::before {
            content: "Unit Price";
            font-size: 12px;
            color: #718096;
            font-weight: 500;
          }
          .cart-qty-cell {
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-top: 1px dashed #edf2f7;
          }
          .cart-qty-cell::before {
            content: "Quantity";
            font-size: 12px;
            color: #718096;
            font-weight: 500;
          }
          .cart-total-cell {
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-top: 1px dashed #edf2f7;
            font-size: 15px;
          }
          .cart-total-cell::before {
            content: "Subtotal";
            font-size: 12px;
            color: #1a202c;
            font-weight: 700;
          }
        }

        /* Empty state */
        .classic-cart-empty {
          text-align: center;
          padding: 80px 24px;
          background: #fff;
          border: 1px solid #e8ecf0;
          border-radius: 12px;
        }
        .classic-cart-empty .empty-icon { font-size: 56px; opacity: 0.3; display: block; margin-bottom: 20px; }
        .classic-cart-empty h4 { font-size: 22px; font-weight: 800; color: #1a202c; margin-bottom: 8px; }
        .classic-cart-empty p { font-size: 14px; color: #a0aec0; margin-bottom: 28px; }
        .classic-cart-empty a {
          display: inline-block;
          background: #3ec1bc;
          color: white;
          padding: 13px 32px;
          border-radius: 8px;
          font-size: 14px;
          font-weight: 700;
          text-decoration: none;
          transition: background 0.15s;
        }
        .classic-cart-empty a:hover { background: #2da8a3; }
      `}</style>

      <section className="classic-cart-section">
        <div className="container">
          <h1 className="classic-cart-title">Shopping Cart</h1>
          <p className="classic-cart-subtitle">
            {cartProducts.length === 0
              ? "Your cart is empty"
              : `${cartProducts.length} item${cartProducts.length > 1 ? "s" : ""} in your cart`}
          </p>

          {/* Free Shipping Progress Banner */}
          {cartProducts.length > 0 && (
            <div className="cart-freeship-banner">
              <div className="freeship-banner-info">
                {amountToFreeship === 0 ? (
                  <span style={{ color: "#166534", fontWeight: "700" }}>
                    🎉 Congratulations! You have unlocked <strong>FREE Shipping!</strong>
                  </span>
                ) : (
                  <span>
                    🚚 Add <strong>{formatPrice(amountToFreeship)}</strong> more to get <strong>FREE Shipping</strong> on your order!
                  </span>
                )}
              </div>
              <div className="freeship-progress-track">
                <div
                  className="freeship-progress-fill"
                  style={{
                    width: `${Math.min(100, Math.round((totalPrice / freeShippingAbove) * 100))}%`,
                    backgroundColor: amountToFreeship === 0 ? "#22c55e" : "#3ec1bc",
                  }}
                />
              </div>
            </div>
          )}

          <div className="row">
            {cartProducts.length === 0 ? (
              <div className="col-12">
                <div className="classic-cart-empty">
                  <span className="empty-icon">🛒</span>
                  <h4>Your cart is empty</h4>
                  <p>Add items from the shop to see them here.</p>
                  <Link to="/shop-default">Continue Shopping</Link>
                </div>
              </div>
            ) : (
              <>
                {/* ── Products Column ── */}
                <div className="col-lg-8 mb-4 mb-lg-0 animate-fade-in-up delay-100">
                  <div className="classic-cart-table-wrap">
                    <table className="classic-cart-table">
                      <thead>
                        <tr>
                          <th style={{ width: "50%" }}>Product</th>
                          <th>Price</th>
                          <th className="col-qty">Quantity</th>
                          <th className="col-total">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        {cartProducts.map((item, idx) => (
                          <CartTableRow
                            key={`${item.id}-${item.selectedVariantId ?? "base"}-${idx}`}
                            item={item}
                            onRemove={() => removeLine(item.id, item.selectedVariantId, idx)}
                            onQtyChange={(qty) => setQty(item.id, qty, item.selectedVariantId, idx)}
                          />
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/* Promo / Voucher */}
                  <div className="classic-promo-wrap">
                    <label>Voucher / Promo Code</label>
                    {appliedCode ? (
                      <div className="promo-applied-row">
                        <span className="promo-applied-text">
                          ✓ <strong>{appliedCode}</strong> applied — you save {formatPrice(promoDiscount)}
                        </span>
                        <button type="button" className="promo-remove-btn" onClick={removePromo}>Remove</button>
                      </div>
                    ) : (
                      <div className="promo-input-row">
                        <input
                          type="text"
                          placeholder="Enter promo code"
                          value={promoInput}
                          onChange={(e) => { setPromoInput(e.target.value.toUpperCase()); setPromoError(""); }}
                          onKeyDown={(e) => e.key === "Enter" && handleApplyPromo()}
                          disabled={promoLoading}
                        />
                        <button
                          className="promo-apply-btn"
                          type="button"
                          onClick={handleApplyPromo}
                          disabled={promoLoading}
                        >
                          {promoLoading ? "…" : "Apply"}
                        </button>
                      </div>
                    )}
                    {promoError && <p className="promo-error">{promoError}</p>}
                  </div>
                </div>

                {/* ── Order Summary Sidebar ── */}
                <div className="col-lg-4 animate-fade-in-up delay-200">
                  <div className="classic-summary-card">
                    <div className="summary-card-header">
                      <h5>Order Summary</h5>
                    </div>
                    <div className="summary-card-body">

                      <div className="summary-line">
                        <span className="label">Subtotal ({cartProducts.length} item{cartProducts.length > 1 ? "s" : ""})</span>
                        <span className="value">{formatPrice(totalPrice)}</span>
                      </div>

                      {discount > 0 && (
                        <div className="summary-line">
                          <span className="label">Discount ({appliedCode})</span>
                          <span className="value discount">−{formatPrice(discount)}</span>
                        </div>
                      )}

                      <div className="summary-line">
                        <span className="label">Shipping</span>
                        <span className={`value${shippingCost === 0 ? " free" : ""}`}>
                          {shippingCost === 0 ? "Free" : formatPrice(shippingCost)}
                        </span>
                      </div>

                      {totalPrice > 0 && totalPrice < freeShippingAbove && (
                        <div className="summary-freeship-note">
                          🚚 Spend {formatPrice(amountToFreeship)} more to unlock <strong>free shipping</strong>
                        </div>
                      )}

                      <div className="summary-divider" />

                      <div className="summary-total-row">
                        <span className="summary-total-label">
                          Total
                        </span>
                        <span className="summary-total-value">{formatPrice(amountDue)}</span>
                      </div>

                      <Link
                        to="/checkout"
                        id="checkout-btn"
                        className="checkout-action-btn"
                        onClick={(e) => {
                          if (!isLoggedIn) {
                            e.preventDefault();
                            useModalStore.getState().openModal("signIn", { redirect: "/checkout" });
                          }
                        }}
                      >
                        Proceed to Checkout
                      </Link>

                      <Link to="/shop-default" className="continue-shopping-link">
                        ← Continue Shopping
                      </Link>

                      <div className="secure-badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                        Secure checkout · SSL encrypted
                      </div>
                    </div>
                  </div>
                </div>
              </>
            )}
          </div>
        </div>
      </section>

      {/* Floating Mobile Bottom Bar */}
      {cartProducts.length > 0 && (
        <div className="mobile-cart-sticky-bar">
          <div className="mobile-bar-info">
            <span className="mobile-bar-label">Total</span>
            <span className="mobile-bar-total">{formatPrice(amountDue)}</span>
          </div>
          <Link
            to="/checkout"
            className="mobile-bar-checkout-btn"
            onClick={(e) => {
              if (!isLoggedIn) {
                e.preventDefault();
                useModalStore.getState().openModal("signIn", { redirect: "/checkout" });
              }
            }}
          >
            <span>Proceed to Checkout</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" /></svg>
          </Link>
        </div>
      )}
    </>
  );
}

const CartTableRow = memo(function CartTableRow({
  item,
  onRemove,
  onQtyChange,
}: {
  item: CartProduct;
  onRemove: () => void;
  onQtyChange: (qty: number) => void;
}) {
  const baseImg = item.img ?? item.images?.[0]?.src ?? "/frontend/assets/images/product/product-1.jpg";
  const imgSrc = apiImageUrl(baseImg);
  const colorLabel = item.selectedColor ?? item.colors?.[0]?.label ?? null;
  const sizeLabel = item.selectedSize ?? null;
  const packLabel = item.unit_label ?? null;
  const lineTotal = item.price * item.quantity;

  return (
    <tr className="tf-cart_item each-prd file-delete">
      {/* Product */}
      <td>
        <div className="cart-product-cell">
          <div className="cart-product-img">
            <Link to={`/product-detail/${item.id}`}>
              <img loading="lazy" src={imgSrc} alt={item.name} />
            </Link>
          </div>
          <div className="cart-product-info">
            <Link to={`/product-detail/${item.id}`} className="product-name">
              {item.name}
            </Link>
            {item.category && (
              <div className="product-meta">Category: <span>{item.category}</span></div>
            )}
            {packLabel && (
              <div className="product-meta">Pack: <span>{packLabel}</span></div>
            )}
            {colorLabel && (
              <div className="product-meta">Color: <span>{colorLabel}</span></div>
            )}
            {sizeLabel && (
              <div className="product-meta">Size: <span>{sizeLabel}</span></div>
            )}
            <button type="button" className="cart-remove-btn" onClick={onRemove}>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
              Remove
            </button>
          </div>
        </div>
      </td>

      {/* Unit Price */}
      <td className="cart-price-cell" data-cart-title="Price">
        {formatPrice(item.price)}
      </td>

      {/* Quantity */}
      <td className="cart-qty-cell" data-cart-title="Quantity">
        <div className="qty-stepper">
          <button
            type="button"
            onClick={() => onQtyChange(item.quantity - 1)}
            aria-label="Decrease quantity"
          >
            −
          </button>
          <span className="qty-val">{item.quantity}</span>
          <button
            type="button"
            onClick={() => {
              if (item.stock !== undefined && item.quantity >= item.stock) return;
              onQtyChange(item.quantity + 1);
            }}
            aria-label="Increase quantity"
            disabled={item.stock !== undefined && item.quantity >= item.stock}
            style={
              item.stock !== undefined && item.quantity >= item.stock
                ? { opacity: 0.5, cursor: "not-allowed" }
                : {}
            }
            title={item.stock !== undefined && item.quantity >= item.stock ? `Only ${item.stock} in stock` : ""}
          >
            +
          </button>
        </div>
      </td>

      {/* Line Total */}
      <td className="cart-total-cell">
        {formatPrice(lineTotal)}
      </td>
    </tr>
  );
});
