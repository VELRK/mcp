import { Link } from "react-router-dom";
import TfSwiper from "@/components/ui/TfSwiper";
import { useSpecialProducts, apiImageUrl } from "@/hooks/useApi";

function Gallery() {
  const { products, loading } = useSpecialProducts(10);

  if (loading) return null;
  if (!products.length) return null;

  return (
    <div className="themesFlat px-10 pb-40">
      <div className="sect-heading sect-heading-classic text-center wow fadeInUp">
        <h3 className="s-title">Curated for you</h3>
        <p className="s-desc text-muted">Handpicked exclusively for you</p>
      </div>

      <TfSwiper
        preview={5}
        tablet={3}
        mobileSm={2}
        mobile={2}
        space={15}
        pagination={2}
        paginationSm={3}
        paginationMd={4}
        paginationLg={5}
        paginationClassName="sw-dot-default tf-sw-pagination"
      >
        {products.map((product, index) => (
          <div
            key={product.id}
            className="wow fadeInUp"
            data-wow-delay={`${0.1 * (index % 5)}s`}
          >
            <Link
              to={`/product-detail/${product.id}`}
              className="classic-gallery-item"
            >
              <div style={{ aspectRatio: "3/4" }}>
                <img
                  src={apiImageUrl(product.thumbnail)}
                  alt={product.name}
                  className="classic-gallery-img"
                  loading="lazy"
                  onError={(e) => {
                    (e.target as HTMLImageElement).src =
                      "/frontend/assets/images/product/product-placeholder.jpg";
                  }}
                />
              </div>
              <div className="classic-gallery-overlay">
                <div className="classic-gallery-content">
                  <h6 className="classic-gallery-title">{product.name}</h6>
                  <span className="classic-gallery-btn">View Details</span>
                </div>
              </div>
            </Link>
          </div>
        ))}
      </TfSwiper>
    </div>
  );
}

export default Gallery;
