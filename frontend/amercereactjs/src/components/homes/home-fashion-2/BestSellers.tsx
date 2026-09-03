import TfSwiper from "@/components/ui/TfSwiper";
import { useProducts, toProductCard } from "@/hooks/useApi";
import type { ProductCardItem } from "@/types/productCard";
import { Link } from "react-router-dom";
import AddToCartButton from "@/components/common/AddToCartButton";
import WishlistButton from "@/components/common/WishlistButton";

import { formatPrice } from "@/utils/formatPrice";

function BestSellerCard({ product }: { product: ProductCardItem }) {
  // Calculate discount percentage if priceOld is available
  const discountPercent = product.priceOld
    ? Math.round(((product.priceOld - product.price) / product.priceOld) * 100)
    : 0;

  return (
    <div
      style={{
        backgroundColor: "#fdf8f4",
        padding: "15px",
        display: "flex",
        flexDirection: "column",
        height: "100%",
        gap: "12px",
      }}
    >
      {/* Image Container */}
      <div style={{ position: "relative", width: "100%", aspectRatio: "1/1", overflow: "hidden", display: "block" }}>
        <div style={{ position: "absolute", top: "8px", right: "8px", zIndex: 10 }}>
          <WishlistButton
            product={product as any}
            className="hover-tooltip tooltip-left box-icon bg-white shadow-sm rounded-circle d-flex align-items-center justify-content-center"
            style={{ width: "34px", height: "34px", border: "1px solid rgba(0,0,0,0.06)" }}
          />
        </div>
        <Link to={`/product-detail/${product.id}`} style={{ width: "100%", height: "100%", display: "block" }}>
          {discountPercent > 0 && (
            <div
              style={{
                position: "absolute",
                top: "10px",
                left: "10px",
                backgroundColor: "#3ec1bc",
                color: "#fff",
                padding: "4px 8px",
                fontSize: "12px",
                fontWeight: "500",
                zIndex: 2,
              }}
            >
              {discountPercent}% OFF
            </div>
          )}
          <img
            src={product.img}
            alt={product.name}
            style={{ width: "100%", height: "100%", objectFit: "cover" }}
          />
        </Link>
      </div>

      {/* Info Container */}
      <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
        {/* Rating */}
        <div style={{ display: "flex", alignItems: "center", gap: "4px", fontSize: "12px", color: "#f5a623" }}>
          <span>★★★★★</span>
          <span style={{ color: "#777" }}>({product.avg_rating || "5.0"})</span>
        </div>

        {/* Title */}
        <Link
          to={`/product-detail/${product.id}`}
          style={{
            fontSize: "14px",
            fontWeight: "500",
            color: "#333",
            display: "-webkit-box",
            WebkitLineClamp: 2,
            WebkitBoxOrient: "vertical",
            overflow: "hidden",
            lineHeight: "1.4",
            minHeight: "39px", // Roughly 2 lines of 14px text with 1.4 line-height
            textDecoration: "none"
          }}
        >
          {product.name}
        </Link>

        {/* Price */}
        <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
          <span style={{ fontSize: "16px", fontWeight: "600", color: "#333" }}>
            {formatPrice(product.price)}
          </span>
          {product.priceOld && (
            <span style={{ fontSize: "13px", color: "#999", textDecoration: "line-through" }}>
              {formatPrice(product.priceOld)}
            </span>
          )}
        </div>
      </div>

      {/* Add to Cart Button */}
      <div style={{ marginTop: "auto", paddingTop: "10px" }}>
        <AddToCartButton
          product={product as any}
          label="Add to Cart +"
          className="tf-btn-reset"
          style={{
            width: "100%",
            backgroundColor: "#3ec1bc",
            color: "#fff",
            border: "none",
            padding: "10px",
            fontSize: "14px",
            fontWeight: "500",
            cursor: "pointer",
            display: "flex",
            justifyContent: "center",
            alignItems: "center",
          }}
        />
      </div>
    </div>
  );
}

function BestSellers() {
  const { products, loading } = useProducts({ special_product: 1, limit: 16 });

  const cards = products.map(toProductCard);

  if (loading) return null;
  if (!cards.length) return null;

  // Fallback seamless pattern or similar if desired.
  const bgPattern = `url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M30,10 C20,20 10,20 10,30 C10,40 20,40 30,50 C40,40 50,40 50,30 C50,20 40,20 30,10 Z' fill='rgba(255, 255, 255, 0.08)' /%3E%3C/svg%3E")`;

  return (
    <section className="flat-spacing" style={{ backgroundColor: "#3ec1bc", backgroundImage: bgPattern, padding: "60px 0", position: "relative" }}>
      <div className="container position-relative">
        <div style={{ textAlign: "center", marginBottom: "40px" }}>
          <h6 style={{ fontSize: "12px", color: "#fff", textTransform: "uppercase", letterSpacing: "1px", marginBottom: "10px", fontWeight: "600" }}>
            Bestsellers
          </h6>
          <h2 style={{ fontSize: "36px", color: "#fff", fontFamily: "serif", fontWeight: "normal", margin: 0, display: "flex", alignItems: "center", justifyContent: "center", gap: "15px" }}>
            <span style={{ fontSize: "24px", opacity: 0.8 }}>⤅</span>
            Curated for you
            <span style={{ fontSize: "24px", opacity: 0.8 }}>⤆</span>
          </h2>
        </div>

        <div className="tab-content position-relative">
          <div className="tab-pane fade active show" role="tabpanel">
              <div style={{ position: "relative" }}>
                <TfSwiper
                  className="wrap-sw-over"
                  preview={4}
                  tablet={3}
                  mobileSm={2}
                  mobile={2}
                  spaceLg={30}
                  spaceMd={20}
                  space={10}
                  paginationDisabled={true}
                  externalNavSelectors={{ prevEl: ".best-seller-prev", nextEl: ".best-seller-next" }}
                >
                  {cards.map((product) => (
                    <BestSellerCard key={product.id} product={product} />
                  ))}
                </TfSwiper>

                {/* Custom Navigation Arrows */}
                <div
                  className="best-seller-prev"
                  style={{ position: "absolute", top: "50%", left: "-45px", transform: "translateY(-50%)", width: "40px", height: "40px", backgroundColor: "#fff", color: "#3ec1bc", borderRadius: "50%", display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer", zIndex: 10, boxShadow: "0 2px 10px rgba(0,0,0,0.15)" }}
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                </div>
                <div
                  className="best-seller-next"
                  style={{ position: "absolute", top: "50%", right: "-45px", transform: "translateY(-50%)", width: "40px", height: "40px", backgroundColor: "#fff", color: "#3ec1bc", borderRadius: "50%", display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer", zIndex: 10, boxShadow: "0 2px 10px rgba(0,0,0,0.15)" }}
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                </div>
              </div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default BestSellers;

