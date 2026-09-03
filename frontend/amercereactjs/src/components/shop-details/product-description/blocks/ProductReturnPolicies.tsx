import type { ProductCardItem } from "@/types/productCard";
import { formatPrice } from "@/utils/formatPrice";

type Props = {
  wrapperClassName?: string;
  titleTag?: "h5" | "div";
  product?: ProductCardItem;
};

function DescTitle({ tag, children }: { tag: "h5" | "div"; children: React.ReactNode }) {
  return tag === "h5"
    ? <h5 className="desc_title mb-4 pb-2 text-uppercase fs-6 fw-bold">{children}</h5>
    : <div className="h6 desc_title mb-4 pb-2 text-uppercase fs-6 fw-bold">{children}</div>;
}

export function ProductReturnPolicies({
  wrapperClassName = "tab-content_desc desc-3 d-grid",
  titleTag = "h5",
  product,
}: Props) {
  const returnPolicy = product?.return_policy;
  const shippingInfo = product?.shipping_info;
  const sla = product?.procurement_sla ?? 3;

  return (
    <div className={wrapperClassName} style={{ gap: '3rem' }}>
      <div className="box-desc">
        <DescTitle tag={titleTag}>Return Policy</DescTitle>
        <div className="desc_info mt-2">
          {returnPolicy ? (
            <div
              className="cl-text-2 product-html-content"
              style={{ lineHeight: '1.8', fontSize: '15px' }}
              dangerouslySetInnerHTML={{ __html: returnPolicy.replace(/<p><br><\/p>/gi, '').replace(/retur policy\s*-\s*/i, '') }}
            />
          ) : (
            <p className="cl-text-2 m-0" style={{ lineHeight: '1.6' }}>
              Easy 7-day return. Items must be unused, unwashed, and in original packaging with tags attached.
            </p>
          )}
        </div>
      </div>

      <div className="box-desc">
        <DescTitle tag={titleTag}>Shipping &amp; Delivery</DescTitle>
        <div className="desc_info mt-2">
          {shippingInfo ? (
            <div
              className="cl-text-2 product-html-content"
              style={{ lineHeight: '1.8', fontSize: '15px' }}
              dangerouslySetInnerHTML={{ __html: shippingInfo }}
            />
          ) : (
            <ul className="list-unstyled">
              <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
                <span className="me-2 text-dark opacity-50">•</span>
                <span>Standard delivery: {sla}–{sla + 3} business days.</span>
              </li>
              <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
                <span className="me-2 text-dark opacity-50">•</span>
                <span>Tracking number shared via SMS/email after dispatch.</span>
              </li>
              <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
                <span className="me-2 text-dark opacity-50">•</span>
                <span>Free shipping on orders above {formatPrice(999)}.</span>
              </li>
            </ul>
          )}
        </div>
      </div>

      <div className="box-desc">
        <DescTitle tag={titleTag}>How to Return</DescTitle>
        <ul className="list-unstyled mt-2">
          <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
            <span className="me-2 text-dark opacity-50">•</span>
            <span>Contact us within the return window via WhatsApp or email.</span>
          </li>
          <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
            <span className="me-2 text-dark opacity-50">•</span>
            <span>Pack the item securely with original packaging and invoice.</span>
          </li>
          <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
            <span className="me-2 text-dark opacity-50">•</span>
            <span>Our courier will pick up or we'll share a prepaid label.</span>
          </li>
          <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
            <span className="me-2 text-dark opacity-50">•</span>
            <span>Refund is processed within 5–7 working days after receipt.</span>
          </li>
        </ul>
      </div>
    </div>
  );
}
