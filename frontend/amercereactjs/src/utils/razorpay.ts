declare global {
  interface Window {
    Razorpay: new (options: object) => {
      open(): void;
      on(event: string, handler: (response: unknown) => void): void;
    };
  }
}

import { paymentAPI } from "@/services/api";
import { removePaidProductsFromCart, type PaidCartLine } from "@/utils/cartSync";

export function razorpayUserMessage(err?: {
  reason?: string;
  description?: string;
  code?: string;
} | null): { kind: "pending" | "failed"; message: string; retry: boolean } {
  const reason = (err?.reason || "").toLowerCase().trim();
  const desc = (err?.description || "").trim();
  const code = (err?.code || "").toUpperCase();

  if (
    reason === "payment_pending_gateway" ||
    /taking more time than usual|being processed|notified shortly/i.test(desc)
  ) {
    return {
      kind: "pending",
      retry: false,
      message:
        "Your payment is still processing. Do not pay again — we will confirm the order automatically. Check My Orders in a few minutes.",
    };
  }

  const byReason: Record<string, string> = {
    card_declined:
      "Card was declined by the bank. Try another card or UPI. Your order is saved under My Orders.",
    payment_session_expired:
      "The payment session expired. Open My Orders and tap Complete payment to try again.",
    payment_timed_out:
      "The bank took too long to respond. Please try again from My Orders.",
    authentication_failed:
      "Card / UPI authentication failed. Try again or use another payment method.",
    insufficient_funds:
      "Insufficient funds. Use another card or UPI.",
  };

  if (byReason[reason]) {
    return { kind: "failed", retry: true, message: byReason[reason] };
  }
  if (code === "GATEWAY_ERROR") {
    return {
      kind: "failed",
      retry: true,
      message:
        "Razorpay had a technical error. Please try again in a few minutes from My Orders.",
    };
  }
  if (desc) {
    return {
      kind: "failed",
      retry: true,
      message: `${desc} Your order is saved under My Orders.`,
    };
  }
  return {
    kind: "failed",
    retry: true,
    message:
      "Payment was not completed. Try again from My Orders. If money was deducted, it is usually returned in 5–7 working days.",
  };
}

export function loadRazorpayScript(): Promise<boolean> {
  return new Promise((resolve) => {
    if (window.Razorpay) {
      resolve(true);
      return;
    }
    const script = document.createElement("script");
    script.src = "https://checkout.razorpay.com/v1/checkout.js";
    script.onload = () => resolve(true);
    script.onerror = () => resolve(false);
    document.body.appendChild(script);
  });
}

type RazorpayPayData = {
  razorpay_order_id: string;
  amount: number;
  currency: string;
  key_id: string;
  order_number?: string;
  prefill?: { name?: string; email?: string; contact?: string };
};

/**
 * Open Razorpay Standard Checkout for an existing shop order (checkout or My Orders retry).
 */
export async function completeOrderPayment(
  orderId: number,
  onMessage: (message: string) => void,
): Promise<"confirmed" | "pending" | "failed" | "cancelled"> {
  const loaded = await loadRazorpayScript();
  if (!loaded) {
    onMessage("Failed to load payment gateway. Please try again.");
    return "failed";
  }

  const payRes = await paymentAPI.createOrder({ order_id: orderId });
  const payData = payRes.data as {
    success?: boolean;
    message?: string;
    data?: RazorpayPayData;
  };
  if (!payData.success || !payData.data?.razorpay_order_id) {
    onMessage(payData.message ?? "Payment gateway error. Please try again.");
    return "failed";
  }

  const pd = payData.data;
  const checkoutLogo = new URL(
    "assets/logo/logo.png",
    window.location.origin + import.meta.env.BASE_URL,
  ).href;

  return new Promise((resolve) => {
    const rzp = new window.Razorpay({
      key: pd.key_id,
      amount: pd.amount,
      currency: pd.currency,
      order_id: pd.razorpay_order_id,
      name: "2Deal",
      description: pd.order_number ? `Order #${pd.order_number}` : "2Deal order",
      image: checkoutLogo,
      prefill: pd.prefill ?? {},
      theme: { color: "#3EC1BC" },
      handler: async (response: {
        razorpay_order_id: string;
        razorpay_payment_id: string;
        razorpay_signature: string;
      }) => {
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
              cart_clear_lines?: PaidCartLine[];
            };
          };
          if (body?.data?.confirmed || body?.success) {
            if (body?.data?.pending) {
              onMessage(body.message || razorpayUserMessage({ reason: "payment_pending_gateway" }).message);
              resolve("pending");
              return;
            }
            if (body?.data?.failed) {
              onMessage(body.message || razorpayUserMessage().message);
              resolve("failed");
              return;
            }
            if (body?.data?.cart_clear_lines?.length) {
              await removePaidProductsFromCart(body.data.cart_clear_lines);
            }
            onMessage(body.message || "Payment successful! Your order is confirmed.");
            resolve("confirmed");
            return;
          }
          if (body?.data?.pending) {
            onMessage(body.message || razorpayUserMessage({ reason: "payment_pending_gateway" }).message);
            resolve("pending");
            return;
          }
          onMessage(body?.message || razorpayUserMessage().message);
          resolve("failed");
        } catch (err: unknown) {
          const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
          onMessage(msg ?? "Payment received but confirmation is still pending. Check My Orders shortly.");
          resolve("pending");
        }
      },
      modal: {
        ondismiss: () => resolve("cancelled"),
      },
    });
    rzp.on("payment.failed", (response: unknown) => {
      const err = (response as {
        error?: { description?: string; reason?: string; code?: string };
      })?.error;
      const mapped = razorpayUserMessage(err);
      onMessage(mapped.message);
      resolve(mapped.kind === "pending" ? "pending" : "failed");
    });
    rzp.open();
  });
}

