import { Link, useNavigate } from "react-router-dom";
import { type FormEvent, useEffect, useState, memo } from "react";
import "./Checkout.css";

import { useContextElement, type CartProduct } from "@/context/Context";
import type { ProductId } from "@/context/store";
import { apiImageUrl } from "@/hooks/useApi";
import { formatPrice, getCurrencySymbol } from "@/utils/formatPrice";
import { useAuthStore } from "@/store/authStore";
import { useModalStore } from "@/store/modalStore";
import { userAPI, cartAPI, ordersAPI, promoAPI, paymentAPI, siteSettingsAPI } from "@/services/api";
import type { ApiAddress } from "@/services/api";
import { loadStoredPromo, saveStoredPromo } from "@/utils/promoStorage";
import { removeLineFromCart, removePaidProductsFromCart } from "@/utils/cartSync";
import { isPlaceholderEmail, isPlaceholderName, isProfileIncomplete } from "@/utils/userProfile";
import { toMalaysiaE164 } from "@/utils/malaysiaPhone";
import { razorpayUserMessage } from "@/utils/razorpay";

/* Razorpay global type */
declare global {
  interface Window {
    Razorpay: new (options: object) => {
      open(): void;
      on(event: string, handler: (response: unknown) => void): void;
    };
  }
}

function loadRazorpayScript(): Promise<boolean> {
  return new Promise((resolve) => {
    if (window.Razorpay) { resolve(true); return; }
    const script = document.createElement("script");
    script.src = "https://checkout.razorpay.com/v1/checkout.js";
    script.onload = () => resolve(true);
    script.onerror = () => resolve(false);
    document.body.appendChild(script);
  });
}



export default function Checkout() {
  const { cartProducts, setCartProducts, updateQuantity, totalPrice } = useContextElement();
  const { isLoggedIn, user } = useAuthStore();
  const navigate = useNavigate();

  /* ── Saved addresses ── */
  const [addresses, setAddresses] = useState<ApiAddress[]>([]);
  const [selectedAddr, setSelectedAddr] = useState<number>(-1);
  const [showAddForm, setShowAddForm] = useState(false);
  const [addressLoading, setAddressLoading] = useState(true); // true until first fetch done

  const loadAddresses = (): Promise<ApiAddress[]> => {
    if (!isLoggedIn) { setAddressLoading(false); return Promise.resolve([]); }
    setAddressLoading(true);
    return userAPI.getAddresses()
      .then((res) => {
        const list = (res.data as { data?: ApiAddress[] }).data ?? [];
        setAddresses(list);
        const defIdx = list.findIndex((a) => Number(a.is_default) === 1);
        if (defIdx >= 0) { setSelectedAddr(defIdx); applyAddress(list[defIdx]); }
        else if (list.length > 0) { setSelectedAddr(0); applyAddress(list[0]); }
        else { setSelectedAddr(-1); setShowAddForm(true); }
        return list;
      })
      .catch(() => { setShowAddForm(true); return []; })
      .finally(() => setAddressLoading(false));
  };

  // Guest checkout: open phone OTP box (not a full login page)
  useEffect(() => {
    if (!isLoggedIn) {
      useModalStore.getState().openModal("signIn", { redirect: "/checkout" });
      navigate("/view-cart", { replace: true });
    }
  }, [isLoggedIn, navigate]);

  /* ── Address form fields ── */
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [companyName, setCompanyName] = useState("");
  // Ignore system-generated placeholder emails (OTP auto-register)
  const realEmail = (email?: string) =>
    email && !isPlaceholderEmail(email) ? email : "";
  const [addrEmail, setAddrEmail] = useState("");
  const [addrPhone, setAddrPhone] = useState("");
  const [addrCity, setAddrCity] = useState("");
  const [addrStreet, setAddrStreet] = useState("");
  const [addrState, setAddrState] = useState("");
  const [addrZip, setAddrZip] = useState("");
  const [, setZipError] = useState(false);
  const [orderNote, setOrderNote] = useState("");
  const needsProfile = isProfileIncomplete(user);
  const needsName = isPlaceholderName(user?.name);
  const needsEmail = isPlaceholderEmail(user?.email);
  const needsAddress = !addressLoading && addresses.length === 0;
  const userId = user?.id ?? null;

  /* ── Order tracking email & profile sync ── */
  const hasExistingEmail = Boolean(user?.email && !isPlaceholderEmail(user.email));
  const [isEditingEmail, setIsEditingEmail] = useState(false);
  const [emailSaving, setEmailSaving] = useState(false);
  const [emailSaveMsg, setEmailSaveMsg] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // Sync isEditingEmail & addrEmail when user account/profile loads or changes
  useEffect(() => {
    if (user?.email && !isPlaceholderEmail(user.email)) {
      setAddrEmail(user.email);
      setIsEditingEmail(false);
    } else {
      setAddrEmail("");
      setIsEditingEmail(true);
    }
  }, [user?.email, user?.id]);

  async function handleSaveEmail(e?: React.MouseEvent | React.FormEvent) {
    if (e) e.preventDefault();
    const trimmed = addrEmail.trim();
    if (!trimmed) {
      setEmailSaveMsg({ type: "error", text: "Please enter an email address." });
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
      setEmailSaveMsg({ type: "error", text: "Please enter a valid email address." });
      return;
    }

    setEmailSaving(true);
    setEmailSaveMsg(null);
    try {
      const res = await userAPI.updateProfile({ email: trimmed });
      const updated = (res.data as { data?: typeof user })?.data;
      if (updated) {
        useAuthStore.getState().setUser(updated);
      }
      setIsEditingEmail(false);
      setEmailSaveMsg({ type: "success", text: "Tracking email updated and saved to your profile." });
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      setEmailSaveMsg({ type: "error", text: msg || "Failed to update email. Please try again." });
    } finally {
      setEmailSaving(false);
    }
  }

  function applyAddress(addr: ApiAddress) {
    const parts = addr.full_name.split(" ");
    setFirstName(parts[0] ?? "");
    setLastName(parts.slice(1).join(" "));
    setCompanyName(addr.company_name ?? "");
    setAddrEmail(realEmail(user?.email));
    setAddrPhone(addr.phone ?? "");
    setAddrCity(addr.city ?? "");
    setAddrStreet(`${addr.line1}${addr.line2 ? ", " + addr.line2 : ""}`);
    setAddrState(addr.state ?? "");
    setAddrZip(addr.pincode ?? "");
    setZipError(false);
    setShowAddForm(false);
  }

  // When account changes (logout → other phone), wipe previous checkout fields
  useEffect(() => {
    setAddresses([]);
    setSelectedAddr(-1);
    setShowAddForm(false);
    setFirstName("");
    setLastName("");
    setCompanyName("");
    setAddrEmail("");
    setAddrPhone("");
    setAddrCity("");
    setAddrStreet("");
    setAddrState("");
    setAddrZip("");
    setOrderNote("");
    if (isLoggedIn) {
      void loadAddresses();
    } else {
      setAddressLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only on account switch
  }, [userId, isLoggedIn]);

  // Prefill from the current account only (never keep another user's values)
  useEffect(() => {
    if (!user) return;
    setAddrPhone(user.phone ?? "");
    setAddrEmail(realEmail(user.email));
    if (user.name && !isPlaceholderName(user.name)) {
      const parts = user.name.trim().split(/\s+/);
      setFirstName(parts[0] ?? "");
      setLastName(parts.slice(1).join(" "));
    }
  }, [userId]);

  /* ── Promo code ── */
  const [promoInput, setPromoInput] = useState("");
  const [appliedCode, setAppliedCode] = useState("");
  const [promoDiscount, setPromoDiscount] = useState(0);
  const [promoError, setPromoError] = useState("");
  const [promoLoading, setPromoLoading] = useState(false);

  /* ── Site Settings (shipping only; GST hidden on storefront) ── */
  const [shippingCharge, setShippingCharge] = useState(50);
  const [freeShippingAbove, setFreeShippingAbove] = useState(999);
  const [currencyCode, setCurrencyCode] = useState("INR");

  useEffect(() => {
    siteSettingsAPI.get().then(res => {
      if (res.data.success && res.data.data) {
        const s = res.data.data;
        if (typeof s.shipping_charge === 'number') setShippingCharge(s.shipping_charge);
        if (typeof s.free_shipping_above === 'number') setFreeShippingAbove(s.free_shipping_above);
        if (s.currency_code) setCurrencyCode(s.currency_code);
      }
    }).catch(err => console.error("Failed to load site settings", err));
  }, []);

  /* Billing address */
  const [billingSame, setBillingSame] = useState(true);
  const [billingCompany, setBillingCompany] = useState("");
  const [billingName, setBillingName] = useState("");
  const [billingPhone, setBillingPhone] = useState("");
  const [billingStreet, setBillingStreet] = useState("");
  const [billingCity, setBillingCity] = useState("");
  const [billingState, setBillingState] = useState("");
  const [billingZip, setBillingZip] = useState("");

  const handleApplyPromo = async (e: FormEvent) => {
    e.preventDefault();
    const code = promoInput.trim().toUpperCase();
    if (!code) return;
    if (!isLoggedIn) { setPromoError("Please log in to apply a promo code."); return; }
    setPromoLoading(true); setPromoError("");
    try {
      const res = await promoAPI.apply({ code, order_amount: totalPrice });
      const r = res.data as { success?: boolean; data?: { discount: number; code: string }; message?: string };
      if (r.success && r.data) {
        setAppliedCode(r.data.code); setPromoDiscount(r.data.discount); setPromoInput("");
        saveStoredPromo({ code: r.data.code, discount: r.data.discount });
      } else setPromoError(r.message ?? "Invalid promo code.");
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      setPromoError(msg ?? "Invalid or expired promo code.");
    } finally { setPromoLoading(false); }
  };

  const removePromo = () => {
    setAppliedCode("");
    setPromoDiscount(0);
    setPromoError("");
    setPromoInput("");
    saveStoredPromo(null);
  };

  /* Restore promo code from cart storage (discount is refreshed below). */
  useEffect(() => {
    if (!isLoggedIn || appliedCode || totalPrice <= 0) return;

    const stored = loadStoredPromo();
    if (stored?.code) {
      setAppliedCode(stored.code);
      // Show stored discount immediately so order totals update before API refresh
      if (stored.discount > 0) setPromoDiscount(stored.discount);
    }
  }, [isLoggedIn, appliedCode, totalPrice]);

  /* Keep coupon discount in sync when cart total changes. */
  useEffect(() => {
    if (!isLoggedIn || !appliedCode || totalPrice <= 0) return;

    let cancelled = false;
    const timer = window.setTimeout(() => {
      setPromoLoading(true);
      promoAPI.apply({ code: appliedCode, order_amount: totalPrice })
        .then((res) => {
          if (cancelled) return;
          const r = res.data as { success?: boolean; data?: { discount: number; code: string }; message?: string };
          if (r.success && r.data) {
            setAppliedCode(r.data.code);
            setPromoDiscount(Number(r.data.discount) || 0);
            setPromoInput("");
            setPromoError("");
            saveStoredPromo({ code: r.data.code, discount: Number(r.data.discount) || 0 });
          } else {
            setAppliedCode("");
            setPromoDiscount(0);
            saveStoredPromo(null);
            setPromoError(r.message ?? "Promo no longer valid for this cart.");
          }
        })
        .catch((err: unknown) => {
          if (cancelled) return;
          setAppliedCode("");
          setPromoDiscount(0);
          saveStoredPromo(null);
          const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
          setPromoError(msg ?? "Could not apply promo code.");
        })
        .finally(() => { if (!cancelled) setPromoLoading(false); });
    }, 250);

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [isLoggedIn, appliedCode, totalPrice]);

  const [paymentMethod, setPaymentMethod] = useState<"cod" | "razorpay">("razorpay");

  // Shipping / free-shipping threshold use amount after coupon
  const subtotalAfterPromo = Math.max(0, totalPrice - promoDiscount);
  const shippingCost = subtotalAfterPromo <= 0
    ? 0
    : (subtotalAfterPromo >= freeShippingAbove ? 0 : shippingCharge);
  const billTotal = Math.max(0, subtotalAfterPromo) + shippingCost;
  const amountDue = billTotal;
  /* ── Place order ── */
  const [orderError, setOrderError] = useState("");
  const [orderPlacing, setOrderPlacing] = useState(false);

  const getDeliveryAddress = () => {
    if (selectedAddr >= 0 && addresses[selectedAddr]) {
      const a = addresses[selectedAddr];
      return {
        full_name: a.full_name,
        company_name: a.company_name ?? "",
        phone: a.phone,
        line1: a.line1,
        line2: a.line2 ?? "",
        city: a.city,
        state: a.state,
        pincode: a.pincode,
        country: a.country || "Malaysia",
      };
    }
    return {
      full_name: `${firstName} ${lastName}`.trim(),
      company_name: companyName.trim(),
      phone: addrPhone,
      line1: addrStreet,
      city: addrCity,
      state: addrState,
      pincode: addrZip,
      country: "Malaysia",
      email: addrEmail.trim(),
    };
  };

  const getBillingAddress = (ship: ReturnType<typeof getDeliveryAddress>) => {
    if (billingSame) {
      return { ...ship };
    }
    return {
      full_name: billingName.trim() || ship.full_name,
      company_name: billingCompany.trim(),
      phone: billingPhone.trim() || ship.phone,
      line1: billingStreet.trim() || ship.line1,
      line2: "",
      city: billingCity.trim() || ship.city,
      state: billingState.trim() || ship.state,
      pincode: billingZip.trim() || ship.pincode,
      country: "Malaysia",
    };
  };

  const handleCheckoutSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setOrderError("");

    if (!isLoggedIn) { setOrderError("Please log in to place an order."); return; }
    if (cartProducts.length === 0) { setOrderError("Your cart is empty."); return; }

    // New OTP users: require a real name (email is optional)
    if (needsProfile || needsAddress || needsName) {
      if (!firstName.trim()) {
        setOrderError("Please enter your full name.");
        return;
      }
    }
    if (addrEmail.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(addrEmail.trim())) {
      setOrderError("Please enter a valid email address, or leave email blank.");
      return;
    }

    const addr = getDeliveryAddress();
    const billing = getBillingAddress(addr);
    if (!addr.full_name || !addr.line1 || !addr.city || !addr.state || !addr.pincode) {
      setOrderError("Please complete the delivery address.");
      return;
    }
    if (!/^\d{5}$/.test(addr.pincode)) { setZipError(true); setOrderError("Enter a valid 5-digit postcode."); return; }
    if (!billingSame) {
      if (!billing.full_name || !billing.line1 || !billing.city || !billing.state || !billing.pincode) {
        setOrderError("Please complete the billing address.");
        return;
      }
    }

    setOrderPlacing(true);
    try {
      // Always persist checkout name (+ optional email) onto the account
      const fullName = `${firstName} ${lastName}`.trim() || addr.full_name;
      if (fullName || addrEmail.trim() || needsProfile || needsEmail) {
        try {
          const profilePayload: { name?: string; email?: string } = {};
          if (fullName) profilePayload.name = fullName;
          // Send email key so backend can store real email or null
          profilePayload.email = addrEmail.trim();
          const profileRes = await userAPI.updateProfile(profilePayload);
          const updated = (profileRes.data as { data?: typeof user })?.data;
          if (updated) useAuthStore.getState().setUser(updated);
        } catch (profileErr: unknown) {
          const msg = (profileErr as { response?: { data?: { message?: string } } })?.response?.data?.message;
          if (msg && /email/i.test(msg)) {
            setOrderError(msg);
            setOrderPlacing(false);
            return;
          }
          console.warn("Checkout profile save:", msg || profileErr);
        }
      }

      // Save delivery address into My Addresses (first order / new address form)
      const shouldSaveAddress = addresses.length === 0 || showAddForm || selectedAddr < 0;
      if (shouldSaveAddress) {
        const phoneForSave = toMalaysiaE164(addr.phone || user?.phone || "");
        try {
          const saveRes = await userAPI.saveAddress({
            full_name: addr.full_name,
            company_name: addr.company_name || "",
            phone: phoneForSave || addr.phone || user?.phone || "",
            line1: addr.line1,
            line2: addr.line2 ?? "",
            city: addr.city,
            state: addr.state,
            pincode: addr.pincode,
            country: addr.country || "Malaysia",
            label: "Home",
            address_type: "shipping",
            is_default: addresses.length === 0 ? 1 : 0,
          });
          const list = (saveRes.data as { data?: { addresses?: ApiAddress[] } })?.data?.addresses
            ?? (await userAPI.getAddresses()).data?.data
            ?? [];
          setAddresses(list);
          setShowAddForm(false);
          if (list.length > 0) {
            const idx = Math.max(0, list.length - 1);
            setSelectedAddr(idx);
            applyAddress(list[idx]);
          }
        } catch (addrErr: unknown) {
          // Backend checkout also persists the address; only block if we have no address book yet
          // and the order path would leave My Addresses empty without a save.
          const msg = (addrErr as { response?: { data?: { message?: string } } })?.response?.data?.message;
          console.warn("Checkout address save failed:", msg || addrErr);
        }
      }

      // 1. Sync local cart → backend cart (collect every stock failure)
      type StockIssue = { name?: string; available?: number; requested?: number };
      const stockMessages: string[] = [];
      await cartAPI.clear();
      for (const item of cartProducts) {
        const title = item.name || `Product #${item.id}`;
        try {
          await cartAPI.add({
            product_id: Number(item.id),
            quantity: item.quantity,
            ...(item.selectedVariantId ? { variant_id: item.selectedVariantId } : {}),
          });
        } catch (addErr: unknown) {
          const body = (addErr as {
            response?: { data?: { message?: string; data?: { stock_issues?: StockIssue[] } } };
          })?.response?.data;
          const issues = body?.data?.stock_issues;
          if (issues && issues.length > 0) {
            for (const s of issues) {
              const label = s.name || title;
              stockMessages.push(
                `'${label}' (available ${s.available ?? 0}, requested ${s.requested ?? item.quantity})`,
              );
            }
          } else if (body?.message) {
            stockMessages.push(body.message.replace(/\.\s*$/, ""));
          } else {
            stockMessages.push(`'${title}' (requested ${item.quantity})`);
          }
        }
      }
      if (stockMessages.length > 0) {
        setOrderError(
          stockMessages.length === 1
            ? `Not enough stock for ${stockMessages[0]}.`
            : `Not enough stock for these items:\n${stockMessages.map((m) => `• ${m}`).join("\n")}`,
        );
        return;
      }

      // 2. If a promo code was applied, re-apply it to the fresh backend cart 
      // to ensure it's not lost after the cart clear/sync.
      if (appliedCode) {
        try {
          await promoAPI.apply({ code: appliedCode, order_amount: totalPrice });
        } catch (e) {
          console.error("Failed to re-apply promo code during checkout sync", e);
        }
      }

      // 3. Place order
      const res = await ordersAPI.checkout({
        address: addr,
        email: addrEmail.trim() || undefined,
        billing_same: billingSame,
        billing_address: billing,
        payment_method: paymentMethod,
        promo_code: appliedCode || undefined,
        coupon: appliedCode || undefined,
        coupon_code: appliedCode || undefined,
        discount_code: appliedCode || undefined,
        voucher: appliedCode || undefined,
        voucher_code: appliedCode || undefined,
        subtotal: totalPrice,
        discount_amount: promoDiscount,
        total: billTotal,
        note: orderNote,
      });

      const result = res.data as {
        success?: boolean;
        message?: string;
        data?: { order?: { id: number }; stock_issues?: StockIssue[] };
      };
      if (!result.success || !result.data?.order?.id) {
        if (result.data?.stock_issues?.length) {
          const parts = result.data.stock_issues.map(
            (s) => `'${s.name}' (available ${s.available ?? 0}, requested ${s.requested ?? 0})`,
          );
          setOrderError(
            parts.length === 1
              ? `Not enough stock for ${parts[0]}.`
              : `Not enough stock for these items:\n${parts.map((m) => `• ${m}`).join("\n")}`,
          );
        } else {
          setOrderError(result.message || "Failed to place order. Please try again.");
        }
        return;
      }

      const orderId = result.data.order.id;
      const paidLines = cartProducts.map((p) => ({
        product_id: Number(p.id),
        variant_id: p.selectedVariantId ?? null,
      }));

      // ── COD or already paid: done ─────────────────
      if (paymentMethod === "cod" || amountDue <= 0.009) {
        saveStoredPromo(null);
        await removePaidProductsFromCart(paidLines);
        setCartProducts([]);
        navigate("/account-orders");
        return;
      }

      // ── Razorpay online payment ─────────────────────────────
      const loaded = await loadRazorpayScript();
      if (!loaded) {
        setOrderError("Failed to load payment gateway. Please try again.");
        return;
      }

      const payRes = await paymentAPI.createOrder({ order_id: orderId });
      const payData = (payRes.data as {
        success?: boolean; data?: {
          razorpay_order_id: string; amount: number; currency: string;
          key_id: string; order_number: string; callback_url?: string;
          prefill: { name: string; email: string; contact: string };
        }; message?: string
      });

      if (!payData.success || !payData.data?.razorpay_order_id) {
        setOrderError(payData.message ?? "Payment gateway error. Please try again.");
        return;
      }

      const pd = payData.data;
      // Same logo as storefront header/home (public/assets/logo/logo.png)
      const checkoutLogo = new URL(
        "assets/logo/logo.png",
        window.location.origin + import.meta.env.BASE_URL,
      ).href;
      const rzpOptions = {
        key: pd.key_id,
        amount: pd.amount,
        currency: pd.currency,
        order_id: pd.razorpay_order_id,
        name: "2Deal",
        description: `Order #${pd.order_number}`,
        image: checkoutLogo,
        prefill: { name: pd.prefill.name, email: pd.prefill.email, contact: pd.prefill.contact },
        theme: { color: "#3EC1BC" },
        handler: async (response: { razorpay_order_id: string; razorpay_payment_id: string; razorpay_signature: string }) => {
          try {
            const verifyRes = await paymentAPI.verify({
              razorpay_order_id: response.razorpay_order_id,
              razorpay_payment_id: response.razorpay_payment_id,
              razorpay_signature: response.razorpay_signature,
              order_id: orderId,
            });
            const body = verifyRes.data as {
              success?: boolean;
              message?: string;
              data?: {
                confirmed?: boolean;
                pending?: boolean;
                failed?: boolean;
                cart_clear_lines?: { product_id: number; variant_id?: number | null }[];
              };
            };
            if (body?.data?.pending) {
              setOrderError(body.message || razorpayUserMessage({ reason: "payment_pending_gateway" }).message);
              setOrderPlacing(false);
              return;
            }
            if (body?.data?.failed || body?.success === false) {
              setOrderError(body.message || razorpayUserMessage().message);
              setOrderPlacing(false);
              return;
            }
            saveStoredPromo(null);
            await removePaidProductsFromCart(
              body?.data?.cart_clear_lines?.length ? body.data.cart_clear_lines : paidLines,
            );
            setCartProducts([]);
            navigate("/account-orders");
            return;
          } catch (err: unknown) {
            const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
            setOrderError(msg ?? ("Payment received but confirmation is still pending. Check My Orders for #" + pd.order_number));
            setOrderPlacing(false);
            return;
          }
        },
        modal: {
          ondismiss: () => {
            // Keep order as payment_attempt — do not cancel. Customer can pay later from Orders.
            setOrderError(
              "Payment not completed. Your order is saved as a payment attempt — complete payment from My Orders when ready."
            );
            setOrderPlacing(false);
          },
        },
      };

      const rzp = new window.Razorpay(rzpOptions);
      rzp.on("payment.failed", (response: unknown) => {
        const err = (response as {
          error?: { description?: string; reason?: string; code?: string };
        })?.error;
        setOrderError(razorpayUserMessage(err).message);
        setOrderPlacing(false);
      });
      rzp.open();
      return; // don't hit finally yet — Razorpay handler controls flow

    } catch (err: unknown) {
      const body = (err as {
        response?: { data?: { message?: string; data?: { stock_issues?: { name?: string; available?: number; requested?: number }[] } } };
      })?.response?.data;
      const issues = body?.data?.stock_issues;
      if (issues && issues.length > 0) {
        const parts = issues.map(
          (s) => `'${s.name}' (available ${s.available ?? 0}, requested ${s.requested ?? 0})`,
        );
        setOrderError(
          parts.length === 1
            ? `Not enough stock for ${parts[0]}.`
            : `Not enough stock for these items:\n${parts.map((m) => `• ${m}`).join("\n")}`,
        );
      } else {
        setOrderError(body?.message ?? "Order placement failed. Please try again.");
      }
    } finally { setOrderPlacing(false); }
  };

  const removeLine = (id: ProductId, variantId?: number, index?: number) => {
    removeLineFromCart(id, variantId, index);
  };
  const setQty = (id: ProductId, qty: number, variantId?: number, index?: number) => {
    if (qty < 1) {
      removeLine(id, variantId, index);
      return;
    }
    updateQuantity(id, qty, variantId);
    cartAPI
      .update({
        product_id: Number(id),
        quantity: qty,
        ...(variantId != null ? { variant_id: variantId } : {}),
      })
      .catch(() => { });
  };

  return (
    <section className="premium-checkout-wrapper animate-fade-in">


      <div className="container">
        <form onSubmit={handleCheckoutSubmit} className="row">
          {/* ── LEFT: address + payment ── */}
          <div className="col-lg-7 animate-fade-in-up delay-100">

            <div className="d-flex align-items-center justify-content-between mb-4">
              <h2 className="checkout-main-title">Checkout</h2>
              <Link to="/view-cart" className="back-to-cart-link">
                ← Back to Cart
              </Link>
            </div>

            {/* Guests are sent to cart with the phone OTP box */}

            <div className="checkout-card">
              <div className="checkout-header">
                <div className="icon">📍</div>
                Delivery Address
              </div>

              {/* Skeleton while addresses are loading */}
              {addressLoading && (
                <div className="mb-4">
                  {[1, 2].map((i) => (
                    <div key={i} className="address-card skeleton-shimmer mb-3" style={{ cursor: "default" }}>
                      <div className="skeleton-line" style={{ height: 14, width: "40%", marginBottom: 10 }} />
                      <div className="skeleton-line" style={{ height: 12, width: "70%", marginBottom: 8 }} />
                      <div className="skeleton-line" style={{ height: 12, width: "55%" }} />
                    </div>
                  ))}
                </div>
              )}

              {!addressLoading && isLoggedIn && addresses.length > 0 && !showAddForm && (
                <div className="mb-4 animate-fade-in">
                  <div className="grid-2">
                    {addresses.map((a, i) => {
                      const isSelected = selectedAddr === i;
                      const labelIcon = a.label === "Work" ? "💼" : a.label === "Hotel" ? "🏨" : a.label === "Parents" ? "👨‍👩‍👧" : "🏠";
                      return (
                        <div
                          key={a.id}
                          className={`address-card ${isSelected ? 'selected' : ''}`}
                          onClick={() => { setSelectedAddr(i); applyAddress(a); }}
                        >
                          <div className="d-flex justify-content-between align-items-start mb-3">
                            <div className="fw-semibold text-dark d-flex align-items-center gap-2" style={{ fontSize: '13px' }}>
                              {labelIcon} {a.label ?? "Home"}
                              {Number(a.is_default) === 1 && <span className="badge address-card-badge">Default</span>}
                            </div>
                            <div className="radio-circle">
                              {isSelected && <div className="radio-inner" />}
                            </div>
                          </div>
                          <div className="address-card-name">{a.full_name}</div>
                          {a.company_name ? (
                            <div className="address-card-details text-muted">{a.company_name}</div>
                          ) : null}
                          <div className="address-card-details">
                            {a.line1}{a.line2 ? `, ${a.line2}` : ""}<br />
                            {a.city}, {a.state} – {a.pincode}
                          </div>
                          <div className="address-card-phone">
                            📞 {a.phone}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                  <button
                    type="button"
                    className="tf-btn-line-2 link mt-3"
                    onClick={() => {
                      setSelectedAddr(-1);
                      setShowAddForm(true);
                      setAddrStreet("");
                      setAddrCity("");
                      setAddrState("");
                      setAddrZip("");
                      setZipError(false);
                    }}
                  >
                    + Add New Address
                  </button>
                </div>
              )}

              {!addressLoading && needsProfile && addresses.length > 0 && !showAddForm && (
                <div className="address-no-data animate-fade-in text-start mb-3">
                  <h5 className="address-no-data-title mb-2">Complete your profile</h5>
                  <p className="address-no-data-desc mb-3">
                    {user?.phone
                      ? `Account: +${String(user.phone).replace(/^\+/, "")}. Enter your real name (email optional).`
                      : "Enter your real name once — saved to this phone account. Email is optional."}
                  </p>
                  <div className="row g-3">
                    {needsName && (
                      <>
                        <div className="col-md-6">
                          <label className="form-label small fw-semibold">First name *</label>
                          <input
                            className="premium-input"
                            value={firstName}
                            onChange={(e) => setFirstName(e.target.value)}
                            required
                            placeholder="First name"
                          />
                        </div>
                        <div className="col-md-6">
                          <label className="form-label small fw-semibold">Last name</label>
                          <input
                            className="premium-input"
                            value={lastName}
                            onChange={(e) => setLastName(e.target.value)}
                            placeholder="Last name"
                          />
                        </div>
                        <div className="col-md-6">
                          <label className="form-label small fw-semibold">Company name <span className="text-muted fw-normal">(optional)</span></label>
                          <input
                            className="premium-input"
                            value={companyName}
                            onChange={(e) => setCompanyName(e.target.value)}
                            placeholder="Company / business name"
                          />
                        </div>
                      </>
                    )}
                    {needsEmail && (
                      <div className="col-md-6">
                        <label className="form-label small fw-semibold">Email <span className="text-muted fw-normal">(optional)</span></label>
                        <input
                          type="email"
                          className="premium-input"
                          value={addrEmail}
                          onChange={(e) => setAddrEmail(e.target.value)}
                          placeholder="you@example.com"
                        />
                      </div>
                    )}
                  </div>
                </div>
              )}

              {!addressLoading && (addresses.length === 0 || showAddForm) && (
                <div className="address-no-data animate-fade-in text-start">
                  <div className="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <h5 className="address-no-data-title mb-0">
                      {addresses.length === 0 && needsProfile
                        ? "Your details & delivery address"
                        : showAddForm
                          ? "New delivery address"
                          : "Delivery address"}
                    </h5>
                    {showAddForm && addresses.length > 0 && (
                      <button
                        type="button"
                        className="tf-btn-line-2 link"
                        onClick={() => {
                          setShowAddForm(false);
                          if (addresses[0]) {
                            setSelectedAddr(0);
                            applyAddress(addresses[0]);
                          }
                        }}
                      >
                        Cancel
                      </button>
                    )}
                  </div>
                  <p className="address-no-data-desc mb-3">
                    {addresses.length === 0 && needsProfile
                      ? "Welcome! Add your name and delivery address. Company and email are optional."
                      : showAddForm
                        ? "This address is used for this order and saved to your account."
                        : "Add a delivery address to continue."}
                  </p>
                  <div className="row g-3 mb-2">
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">First name *</label>
                      <input
                        className="premium-input"
                        value={firstName}
                        onChange={(e) => setFirstName(e.target.value)}
                        required
                        placeholder="First name"
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">Last name</label>
                      <input
                        className="premium-input"
                        value={lastName}
                        onChange={(e) => setLastName(e.target.value)}
                        placeholder="Last name"
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">Company name <span className="text-muted fw-normal">(optional)</span></label>
                      <input
                        className="premium-input"
                        value={companyName}
                        onChange={(e) => setCompanyName(e.target.value)}
                        placeholder="Company / business name"
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">
                        Email {needsEmail ? <span className="text-muted fw-normal">(optional)</span> : null}
                      </label>
                      <input
                        type="email"
                        className="premium-input"
                        value={addrEmail}
                        onChange={(e) => setAddrEmail(e.target.value)}
                        placeholder="you@example.com"
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">Mobile *</label>
                      <input
                        className="premium-input"
                        value={addrPhone}
                        onChange={(e) => setAddrPhone(e.target.value)}
                        required
                        placeholder="Mobile number"
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">Address line *</label>
                      <input
                        className="premium-input"
                        value={addrStreet}
                        onChange={(e) => setAddrStreet(e.target.value)}
                        required
                        placeholder="House / street / area"
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">City *</label>
                      <input
                        className="premium-input"
                        value={addrCity}
                        onChange={(e) => setAddrCity(e.target.value)}
                        required
                        placeholder="City"
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">State *</label>
                      <input
                        className="premium-input"
                        value={addrState}
                        onChange={(e) => setAddrState(e.target.value)}
                        required
                        placeholder="State"
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">Postcode *</label>
                      <input
                        className="premium-input"
                        value={addrZip}
                        onChange={(e) => setAddrZip(e.target.value.replace(/\D/g, "").slice(0, 5))}
                        required
                        placeholder="12345"
                        maxLength={5}
                      />
                    </div>
                  </div>
                </div>
              )}
              <textarea className="premium-input mt-4 mb-0" placeholder="Order notes" rows={2} value={orderNote} onChange={(e) => setOrderNote(e.target.value)} />
            </div>

            <div className="checkout-card">
              <div className="checkout-header">
                <div className="icon">🧾</div>
                Billing Address
              </div>
              <label className="d-flex align-items-center gap-2 mb-3" style={{ cursor: "pointer" }}>
                <input
                  type="checkbox"
                  checked={billingSame}
                  onChange={(e) => setBillingSame(e.target.checked)}
                />
                <span>Same as delivery address</span>
              </label>
              {!billingSame && (
                <div className="animate-fade-in">
                  <input className="premium-input mb-2" placeholder="Company name (optional)" value={billingCompany} onChange={(e) => setBillingCompany(e.target.value)} />
                  <input className="premium-input mb-2" placeholder="Full name *" value={billingName} onChange={(e) => setBillingName(e.target.value)} required={!billingSame} />
                  <input className="premium-input mb-2" placeholder="Phone *" value={billingPhone} onChange={(e) => setBillingPhone(e.target.value)} />
                  <input className="premium-input mb-2" placeholder="Address line *" value={billingStreet} onChange={(e) => setBillingStreet(e.target.value)} />
                  <div className="row g-2">
                    <div className="col-md-4"><input className="premium-input" placeholder="City *" value={billingCity} onChange={(e) => setBillingCity(e.target.value)} /></div>
                    <div className="col-md-4"><input className="premium-input" placeholder="State *" value={billingState} onChange={(e) => setBillingState(e.target.value)} /></div>
                    <div className="col-md-4"><input className="premium-input" placeholder="Postcode *" value={billingZip} onChange={(e) => setBillingZip(e.target.value)} /></div>
                  </div>
                </div>
              )}
            </div>

            {/* ── Order Tracking Email Card (Under Billing Address) ── */}
            <div className="checkout-card checkout-email-card animate-fade-in">
              <div className="checkout-header">
                <div className="icon">📧</div>
                Order Tracking & Receipt Email
              </div>
              <p className="checkout-email-desc mb-3">
                Order confirmation, live tracking updates, and invoices will be sent to this email address.
              </p>

              {!isEditingEmail && hasExistingEmail ? (
                <div className="checkout-email-display-box">
                  <div className="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div className="d-flex align-items-center gap-3">
                      <div className="checkout-email-icon-badge">✉️</div>
                      <div>
                        <div className="checkout-email-address">{user?.email}</div>
                        <div className="checkout-email-sub">
                          Saved in your profile • Instant updates enabled
                        </div>
                      </div>
                    </div>
                    <button
                      type="button"
                      className="btn-edit-email"
                      onClick={() => {
                        setIsEditingEmail(true);
                        setEmailSaveMsg(null);
                      }}
                    >
                      <span>✏️</span> Edit Email
                    </button>
                  </div>
                </div>
              ) : (
                <div className="checkout-email-edit-box animate-fade-in">
                  <label className="form-label small fw-semibold text-dark mb-1 d-block">
                    {hasExistingEmail ? "Update Tracking Email" : "Enter Tracking Email"}
                  </label>
                  <div className="d-flex gap-2 flex-wrap sm-flex-nowrap align-items-center">
                    <input
                      type="email"
                      className="premium-input flex-grow-1"
                      placeholder="you@example.com"
                      value={addrEmail}
                      onChange={(e) => {
                        setAddrEmail(e.target.value);
                        if (emailSaveMsg) setEmailSaveMsg(null);
                      }}
                    />
                    <button
                      type="button"
                      className="btn-save-email"
                      disabled={emailSaving || !addrEmail.trim()}
                      onClick={handleSaveEmail}
                    >
                      {emailSaving ? "Saving..." : hasExistingEmail ? "Update Profile" : "Save to Profile"}
                    </button>
                    {hasExistingEmail && (
                      <button
                        type="button"
                        className="btn-cancel-email"
                        disabled={emailSaving}
                        onClick={() => {
                          setAddrEmail(realEmail(user?.email));
                          setIsEditingEmail(false);
                          setEmailSaveMsg(null);
                        }}
                      >
                        Cancel
                      </button>
                    )}
                  </div>
                  <div className="checkout-email-hint">
                    💡 This email is saved in your profile and used to send order tracking updates.
                  </div>
                </div>
              )}

              {emailSaveMsg && (
                <div
                  className={`checkout-email-alert mt-3 ${emailSaveMsg.type === "success" ? "alert-success-custom" : "alert-error-custom"
                    }`}
                >
                  {emailSaveMsg.type === "success" ? "✓ " : "⚠️ "}
                  {emailSaveMsg.text}
                </div>
              )}
            </div>

            <div className="checkout-card">
              <div className="checkout-header">
                <div className="icon">💳</div>
                Payment Method
              </div>

              <div
                className={`payment-card mb-3 ${paymentMethod === 'razorpay' ? 'selected' : ''}`}
                onClick={() => setPaymentMethod("razorpay")}
              >
                <div className="d-flex align-items-center gap-3">
                  <div className="radio-circle">
                    {paymentMethod === 'razorpay' && <div className="radio-inner" />}
                  </div>
                  <div>
                    <div className="payment-card-title">💳 Online Payment (Razorpay)</div>
                    <div className="payment-card-desc">UPI · Credit/Debit Card · Net Banking</div>
                  </div>
                </div>
                {paymentMethod === 'razorpay' && (
                  <div className="payment-details-razorpay animate-fade-in">
                    <div className="d-flex gap-2 flex-wrap">
                      {["UPI", "Visa", "Mastercard", "RuPay", "Net Banking"].map((m) => (
                        <span key={m} className="payment-badge">
                          {m}
                        </span>
                      ))}
                    </div>
                    <p className="payment-secure-text mb-0">
                      Secure checkout in {currencyCode} ({getCurrencySymbol()})
                      🔒 Secured by Razorpay — 256-bit SSL encryption
                    </p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* ── RIGHT: order summary ── */}
          <div className="col-lg-5 animate-fade-in-up delay-200">
            <div className="summary-card">
              <h3 className="summary-card-title">Order Summary</h3>

              <div className="order-items-list mb-4">
                {cartProducts.length === 0 ? (
                  <div className="text-center py-4 text-muted fw-semibold">Your cart is empty</div>
                ) : (
                  cartProducts.map((item, idx) => (
                    <CheckoutOrderItemPremium
                      key={`${item.id}-${item.selectedVariantId ?? "base"}-${idx}`}
                      item={item}
                      onRemove={() => removeLine(item.id, item.selectedVariantId, idx)}
                      onQtyChange={(qty) => setQty(item.id, qty, item.selectedVariantId, idx)}
                    />
                  ))
                )}
              </div>

              {appliedCode ? (
                <div className="premium-applied-promo-alert animate-fade-in">
                  <div className="fw-semibold">
                    ✓ {appliedCode} applied!
                  </div>
                  <button type="button" className="btn btn-sm btn-link text-danger p-0 text-decoration-none fw-semibold" onClick={removePromo}>Remove</button>
                </div>
              ) : (
                <div className="promo-box">
                  <input type="text" className="promo-input" placeholder="Promo / voucher code"
                    value={promoInput} onChange={(e) => { setPromoInput(e.target.value); setPromoError(""); }}
                    disabled={promoLoading} />
                  <button className="tf-btn btn-sm animate-btn promo-apply-btn" type="button" onClick={handleApplyPromo} disabled={promoLoading}>
                    {promoLoading ? "..." : "Apply"}
                  </button>
                </div>
              )}
              {promoError && <p className="promo-error-text">{promoError}</p>}

              <div className="summary-row">
                <span>Subtotal</span>
                <span className="fw-semibold text-dark">{formatPrice(totalPrice)}</span>
              </div>
              {promoDiscount > 0 && (
                <div className="summary-row text-success fw-semibold">
                  <span>Discount ({appliedCode})</span>
                  <span>−{formatPrice(promoDiscount)}</span>
                </div>
              )}
              <div className="summary-row">
                <span>Shipping</span>
                <span className="fw-semibold text-dark">{
                  totalPrice <= 0
                    ? formatPrice(0)
                    : shippingCost === 0
                      ? <span className="text-success">Free</span>
                      : formatPrice(shippingCost)
                }</span>
              </div>
              <div className="summary-row fw-semibold">
                <span>Bill total</span>
                <span>{formatPrice(billTotal)}</span>
              </div>

              <div className="summary-total">
                <span>Total</span>
                <span>{formatPrice(amountDue)}</span>
              </div>

              {orderError && (
                <div className="alert alert-danger checkout-error-alert animate-fade-in mt-4 mb-0" style={{ whiteSpace: "pre-line" }}>
                  {orderError}
                </div>
              )}
              {isLoggedIn ? (
                <button type="submit" className="btn-premium mt-4" disabled={cartProducts.length === 0 || orderPlacing}>
                  {orderPlacing
                    ? "Processing..."
                    : `Place Order • ${formatPrice(amountDue)}`}
                  {!orderPlacing && <i className="icon-arrow-right ms-2" />}
                </button>
              ) : (
                <button type="button" className="btn-premium mt-4" data-bs-toggle="modal" data-bs-target="#phoneOTPModal">
                  📱 Login to Place Order
                </button>
              )}

            </div>
          </div>

        </form>
      </div>
    </section>
  );
}

const CheckoutOrderItemPremium = memo(function CheckoutOrderItemPremium({ item, onRemove, onQtyChange }: {
  item: CartProduct; onRemove: () => void; onQtyChange: (qty: number) => void;
}) {
  const baseImg = item.img ?? item.images?.[0]?.src ?? "/frontend/assets/images/product/product-1.jpg";
  const imgSrc = apiImageUrl(baseImg);
  const colorLabel = item.selectedColor ?? item.colors?.[0]?.label ?? null;
  const sizeLabel = item.selectedSize ?? null;

  return (
    <div className="order-item-premium">
      <img src={imgSrc} alt={item.name} />
      <div className="order-item-details">
        <div className="d-flex justify-content-between align-items-start">
          <Link to={`/product-detail/${item.id}`} className="order-item-title text-decoration-none">{item.name}</Link>
          <button type="button" className="btn btn-sm text-danger p-0 border-0 bg-transparent" onClick={onRemove} title="Remove">
            <i className="icon-X2" style={{ fontSize: 16 }} />
          </button>
        </div>

        <div className="order-item-meta">
          {colorLabel && <span className="me-3">Color: <span className="fw-semibold text-dark">{colorLabel}</span></span>}
          {sizeLabel && <span>Size: <span className="fw-semibold text-dark">{sizeLabel}</span></span>}
        </div>

        <div className="d-flex justify-content-between align-items-center mt-auto">
          <div className="qty-control-wrapper d-flex flex-column">
            <div className="qty-control">
              <button type="button" className="qty-btn" onClick={() => onQtyChange(item.quantity - 1)}>−</button>
              <input className="qty-input" readOnly value={item.quantity} />
              <button
                type="button"
                className="qty-btn"
                onClick={() => {
                  if (item.stock !== undefined && item.quantity >= item.stock) return;
                  onQtyChange(item.quantity + 1);
                }}
                disabled={item.stock !== undefined && item.quantity >= item.stock}
                style={item.stock !== undefined && item.quantity >= item.stock ? { opacity: 0.5, cursor: "not-allowed" } : {}}
              >+</button>
            </div>
            {item.stock !== undefined && item.quantity >= item.stock && (
              <span className="text-danger mt-1" style={{ fontSize: "10px", lineHeight: "1" }}>Max stock reached</span>
            )}
          </div>
          <div className="order-item-price">
            {formatPrice(item.price * item.quantity)}
          </div>
        </div>
      </div>
    </div>
  );
});