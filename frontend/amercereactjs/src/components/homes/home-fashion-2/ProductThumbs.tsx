import { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { Swiper, SwiperSlide } from "swiper/react";
import type { Swiper as SwiperType } from "swiper";
import { Controller, EffectFade, Navigation } from "swiper/modules";
import { useProducts, toProductCard, apiImageUrl } from "@/hooks/useApi";
import { formatPrice } from "@/utils/formatPrice";
import AddToCartButton from "@/components/common/AddToCartButton";
import WishlistButton from "@/components/common/WishlistButton";

/** Strip HTML tags and decode common entities to get plain text. */
function stripHtml(html: string): string {
  const doc = new DOMParser().parseFromString(html, "text/html");
  return doc.body.textContent?.trim() ?? "";
}

function ProductThumbs() {
  const mainRef = useRef<SwiperType | null>(null);
  const thumbRef = useRef<SwiperType | null>(null);
  const [linked, setLinked] = useState(false);

  const { products, loading } = useProducts({ sort: "newest", limit: 6 });
  const cards = products.map(toProductCard);

  useEffect(() => {
    if (!linked || !mainRef.current || !thumbRef.current) return;
    const main = mainRef.current;
    const thumb = thumbRef.current;
    main.controller.control = thumb;
    thumb.controller.control = main;
    return () => {
      try {
        if (main.controller) main.controller.control = undefined;
        if (thumb.controller) thumb.controller.control = undefined;
      } catch {
        /* instances may already be destroyed */
      }
    };
  }, [linked]);

  if (loading || cards.length === 0) return null;

  const mainImages = cards.map((c) => apiImageUrl(
    products.find((p) => p.id === c.id)?.images?.[0]?.image
    ?? products.find((p) => p.id === c.id)?.thumbnail
  ));

  return (
    <section className="section-thumbs-v2 tf-sw-thumbs">
      <div className="col-right d-none d-lg-block">
        <Swiper
          modules={[Controller, EffectFade]}
          effect="fade"
          onSwiper={(sw) => {
            mainRef.current = sw;
            setLinked((p) => p || !!thumbRef.current);
          }}
          dir="ltr"
          className="swiper sw-thumb"
        >
          {mainImages.map((img, i) => (
            <SwiperSlide key={i}>
              <div
                className="sw-image"
                style={{ aspectRatio: "1/1", overflow: "hidden", width: "100%" }}
              >
                <img
                  src={img}
                  alt=""
                  width={960}
                  height={960}
                  loading="lazy"
                  style={{ width: "100%", height: "100%", objectFit: "contain" }}
                />
              </div>
            </SwiperSlide>
          ))}
        </Swiper>
      </div>
      <div className="col-left">
        <div className="sect-heading type-2 wow fadeInUp">
          <h3 className="s-title">{cards[0]?.name ?? "New Arrivals"}</h3>
          <p className="cl-text-2">
            {stripHtml(cards[0]?.description) || "Discover our latest collection."}
          </p>
        </div>
        <div dir="ltr" className="swiper sw-main-thumb">
          <Swiper
            modules={[Controller, Navigation]}
            onSwiper={(sw) => {
              thumbRef.current = sw;
              setLinked((p) => p || !!mainRef.current);
            }}
            navigation={{
              nextEl: ".tes_thumb .nav-next-swiper",
              prevEl: ".tes_thumb .nav-prev-swiper",
            }}
            className="sw-main-thumb-inner"
          >
            {cards.map((card) => (
              <SwiperSlide key={card.id}>
                <div className="thumbs-prd wow fadeInUp">
                  <div
                    className="prd-image"
                    style={{ aspectRatio: "3/4", overflow: "hidden" }}
                  >
                    <img
                      src={card.img}
                      alt={card.name}
                      width={330}
                      height={440}
                      loading="lazy"
                      style={{ width: "100%", height: "100%", objectFit: "cover" }}
                    />
                  </div>
                  <div className="prd-mini">
                    <div
                      className="mini-image"
                      style={{ width: 88, height: 100, overflow: "hidden", flexShrink: 0 }}
                    >
                      <img
                        src={card.img}
                        alt={card.name}
                        width={88}
                        height={100}
                        loading="lazy"
                        style={{ width: "100%", height: "100%", objectFit: "cover" }}
                      />
                    </div>
                    <div className="mini-infor">
                      <Link
                        to={`/product-detail/${card.slug ?? card.id}`}
                        className="info_name text-body-1 fw-medium link-underline-primary text-line-clamp-2"
                      >
                        {card.name}
                      </Link>
                      <div className="info_price price-wrap">
                        <span className="price-new text-primary fw-semibold">
                          {formatPrice(card.price)}
                        </span>
                        {card.priceOld && (
                          <span className="price-old text-caption-01 cl-text-3">
                            {formatPrice(card.priceOld)}
                          </span>
                        )}
                      </div>
                    </div>
                    <div className="mini-action d-flex align-items-center gap-2">
                      <WishlistButton
                        product={card}
                        className="hover-tooltip tooltip-left box-icon"
                      />
                      <AddToCartButton
                        product={card}
                        variant="tooltip"
                        className="btn-action hover-tooltip tooltip-left box-icon"
                      />
                    </div>
                  </div>
                </div>
              </SwiperSlide>
            ))}
          </Swiper>
          <div className="tes_thumb">
            <div className="tf-sw-nav-circle nav-prev-swiper">
              <i className="icon icon-CaretLeft" aria-hidden />
            </div>
            <div className="tf-sw-nav-circle nav-next-swiper">
              <i className="icon icon-CaretRightThin" aria-hidden />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default ProductThumbs;
