import TfSwiper from "@/components/ui/TfSwiper";
import { OfferCard } from "@/components/homes/home-fashion-2/OfferCard";
import { useProducts, toProductCard } from "@/hooks/useApi";

function Offers() {
  // Fetch products marked for homepage offers (hot_sale flag)
  const { products, loading } = useProducts({ hot_sale: 1, limit: 10 });

  const cards = products.map(toProductCard);

  if (loading) return null;
  if (!cards.length) return null;

  return (
    <div className="flat-spacing">
      <div className="container">
        <div className="d-flex justify-content-center align-items-center" style={{ marginBottom: "2.5rem" }}>
          <a href="#popular" role="tab" aria-selected className="tf-btn-tab active text-center">
            <h2 style={{ fontSize: "clamp(28px, 6vw, 36px)", color: "#54101d", fontFamily: "serif", fontWeight: "normal", margin: 0 }}>Offer Collections</h2>
          </a>
        </div>

          <div className="tf-btn-swiper-main position-relative">
            <TfSwiper
              className="wrap-sw-over"
              preview={5}
              tablet={4}
              mobileSm={2.5}
              mobile={2}
              spaceLg={30}
              spaceMd={20}
              space={10}
              paginationDisabled
              useExternalNav
            >
              {cards.map((product) => (
                <OfferCard key={product.id} product={product} />
              ))}
            </TfSwiper>
            <div
              className="nav-prev-swiper"
              style={{
                position: "absolute",
                top: "50%",
                left: "-20px",
                transform: "translateY(-50%)",
                zIndex: 10,
                width: "40px",
                height: "40px",
                borderRadius: "50%",
                backgroundColor: "#fff",
                boxShadow: "0 2px 10px rgba(0,0,0,0.1)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                cursor: "pointer",
                border: "1px solid #eaeaea"
              }}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </div>
            <div
              className="nav-next-swiper"
              style={{
                position: "absolute",
                top: "50%",
                right: "-20px",
                transform: "translateY(-50%)",
                zIndex: 10,
                width: "40px",
                height: "40px",
                borderRadius: "50%",
                backgroundColor: "#fff",
                boxShadow: "0 2px 10px rgba(0,0,0,0.1)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                cursor: "pointer",
                border: "1px solid #eaeaea"
              }}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </div>
          </div>
      </div>
    </div>
  );
}

export default Offers;
