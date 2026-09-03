import { Link } from "react-router-dom";
import TfSwiper from "@/components/ui/TfSwiper";
import ProductCard from "@/components/ui/ProductCard";
import { useProducts, toProductCard } from "@/hooks/useApi";

function ProductFeature() {
  const { products, loading } = useProducts({ featured: 1, limit: 9 });

  const cards = products.map(toProductCard);

  if (loading) return null;
  if (!cards.length) return null;

  return (
    <section className="flat-spacing">
      <div className="container-full">
        <div className="row">
          <div className="col-lg-3">
            <div className="sect-heading wow fadeInUp">
              <h3 className="s-title mb-8">New Arrival</h3>
              <p className="text-body-1 cl-text-2">
                Latest products freshly added to the store.
              </p>
            </div>
            <Link to="/shop-default?featured=1" className="tf-btn animate-btn wow fadeInUp mb-30">
              View All New Arrivals
            </Link>
          </div>
          <div className="col-lg-9">
              <TfSwiper
                className="wrap-sw-over"
                preview={3}
                tablet={3}
                mobileSm={2}
                mobile={2}
                spaceLg={30}
                spaceMd={20}
                space={10}
                pagination={2}
                paginationSm={2}
                paginationMd={3}
                paginationLg={3}
                paginationClassName="sw-dot-default tf-sw-pagination"
              >
                {cards.map((product, index) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    imgWidth={330}
                    imgHeight={440}
                    actionBotLabel={index === 1 ? "Add to Cart" : "Quick Add"}
                    actionBotHref={index === 1 ? "#shoppingCart" : "#quickAdd"}
                    actionBotDataToggle={index === 1 ? "offcanvas" : "modal"}
                  />
                ))}
              </TfSwiper>
          </div>
        </div>
      </div>
    </section>
  );
}

export default ProductFeature;
