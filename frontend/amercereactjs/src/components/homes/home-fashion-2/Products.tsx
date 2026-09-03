import TfSwiper from "@/components/ui/TfSwiper";
import ProductCard from "@/components/ui/ProductCard";
import { useProducts, toProductCard } from "@/hooks/useApi";

function Products() {
  const { products, loading } = useProducts({ featured: 1, limit: 16 });

  const cards = products.map(toProductCard);

  if (loading) return null;
  if (!cards.length) return null;

  return (
    <div className="flat-spacing pt-4 pb-5 flat-animate-tab">
      <div className="container">
        <div className="d-flex flex-column align-items-center text-center mb-4">
          <span
            style={{
              fontSize: "12px",
              fontWeight: 700,
              color: "#3ec1bc",
              textTransform: "uppercase",
              letterSpacing: "1.5px",
              marginBottom: "4px",
            }}
          >
            Fresh Collection
          </span>
          <h2
            style={{
              fontSize: "clamp(28px, 4vw, 36px)",
              color: "#54101d",
              fontFamily: "serif",
              fontWeight: "normal",
              margin: 0,
            }}
          >
            New Arrival
          </h2>
        </div>

        <div className="tab-content">
          <div className="tab-pane fade active show" role="tabpanel">
              <TfSwiper
                className="wrap-sw-over"
                preview={4}
                tablet={3}
                mobileSm={2}
                mobile={2}
                spaceLg={24}
                spaceMd={16}
                space={12}
                pagination={2}
                paginationSm={2}
                paginationMd={3}
                paginationLg={4}
                grid={2}
                paginationClassName="sw-dot-default tf-sw-pagination"
              >
                {cards.map((product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    variant="classic"
                    imgWidth={330}
                    imgHeight={330}
                    actionBotLabel="ADD TO CART"
                    actionBotHref="#shoppingCart"
                    actionBotDataToggle="offcanvas"
                  />
                ))}
              </TfSwiper>
          </div>
        </div>
      </div>
    </div>
  );
}

export default Products;
