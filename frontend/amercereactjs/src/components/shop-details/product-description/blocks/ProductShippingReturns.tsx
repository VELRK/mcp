import type { ProductCardItem } from "@/types/productCard";
import { formatPrice } from "@/utils/formatPrice";

type Props = {
  gridClassName?: string;
  titleTag?: "h5" | "div";
  product?: ProductCardItem;
};

function DescTitle({ tag, children }: { tag: "h5" | "div"; children: React.ReactNode }) {
  return tag === "h5"
    ? <h5 className="desc_title mb-4 pb-2 text-uppercase fs-6 fw-bold">{children}</h5>
    : <div className="h6 desc_title mb-4 pb-2 text-uppercase fs-6 fw-bold">{children}</div>;
}

export function ProductShippingReturns({
  gridClassName = "tab-content_desc desc-2 tf-grid-layout sm-col-2 xl-col-4",
  titleTag = "h5",
  product,
}: Props) {
  const sla = product?.procurement_sla ?? null;
  const rawShipping = product?.shipping_info ?? "";
  const shippingInfo = rawShipping && !rawShipping.toLowerCase().includes("we ship across")
    ? rawShipping
    : null;

  return (
    <div className={gridClassName} style={{ gap: '3rem' }}>
      <div className="box-desc">
        <DescTitle tag={titleTag}>Shipping Info</DescTitle>
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
                <span>Free shipping on orders above {formatPrice(999)}</span>
              </li>
              <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
                <span className="me-2 text-dark opacity-50">•</span>
                <span>Tracked dispatch via courier</span>
              </li>
              <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
                <span className="me-2 text-dark opacity-50">•</span>
                <span>Tracked dispatch via courier</span>
              </li>
            </ul>
          )}
        </div>
      </div>

      {sla != null && (
        <div className="box-desc">
          <DescTitle tag={titleTag}>Estimated Delivery</DescTitle>
          <ul className="list-unstyled mt-2">
            <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
              <span className="me-2 text-dark opacity-50">•</span>
              <span>Standard: {sla}–{sla + 3} business days</span>
            </li>
            {product?.procurement_type === "MADE_TO_ORDER" && (
              <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
                <span className="me-2 text-dark opacity-50">•</span>
                <span>Made to order — allow extra production time</span>
              </li>
            )}
            {product?.origin_state && (
              <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
                <span className="me-2 text-dark opacity-50">•</span>
                <span>Ships from {product.origin_state}, India</span>
              </li>
            )}
          </ul>
        </div>
      )}

      <div className="box-desc">
        <DescTitle tag={titleTag}>Need Help?</DescTitle>
        <ul className="list-unstyled mt-2">
          <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
            <span className="me-2 text-dark opacity-50">•</span>
            <span>Contact our support team</span>
          </li>
          <li className="cl-text-2 mb-3 d-flex align-items-start" style={{ lineHeight: '1.6' }}>
            <span className="me-2 text-dark opacity-50">•</span>
            <span>WhatsApp assistance available</span>
          </li>
        </ul>
      </div>
    </div>
  );
}
