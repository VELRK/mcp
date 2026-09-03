import { Link } from "react-router-dom";
import { useCategories, useNavProducts, apiImageUrl } from "@/hooks/useApi";
import type { ApiCategory } from "@/services/api";
import { formatPrice } from "@/utils/formatPrice";

function catLink(c: ApiCategory) {
  return `/shop-default?category_id=${c.id}`;
}
function subLink(s: ApiCategory) {
  return `/shop-default?subcategory_id=${s.id}`;
}

export default function Nav({ variant2: _v2 = false, variant3: _v3 = false }: { variant2?: boolean; variant3?: boolean }) {
  const { categories } = useCategories();   // GET /2Deal-api/categories
  useNavProducts(2); // GET /2Deal-api/products?nav_featured=1&limit=2

  if (!categories.length) return null; // nothing to show while loading

  return (
    <>
      {categories.map((cat) => {
        const hasSub = (cat.children ?? []).length > 0;

        // Group subcategories by mega_group column (columns follow mega menu title order)
        const groups: Record<string, ApiCategory[]> = {};
        (cat.children ?? []).forEach((sub) => {
          const g = sub.mega_group || "All";
          if (!groups[g]) groups[g] = [];
          groups[g].push(sub);
        });
        const groupNames = Object.keys(groups).sort((a, b) => {
          const aOrder = groups[a][0]?.mega_title_sort_order ?? 9999;
          const bOrder = groups[b][0]?.mega_title_sort_order ?? 9999;
          if (aOrder !== bOrder) return aOrder - bOrder;
          return a.localeCompare(b);
        });

        return (
          <li key={cat.id} className="menu-item">
            <Link to={catLink(cat)} className="item-link" style={{ whiteSpace: "nowrap" }}>
              <span className="text cus-text">{cat.name}</span>
              {hasSub && <i className="icon icon-CaretDown" aria-hidden />}
            </Link>

            {hasSub && (
              <div className="sub-menu mega-menu">
                <div className="container-full">
                  <div className="row">

                    {/* Subcategory columns grouped by mega_group — always first */}
                    {groupNames.map((gn) => (
                      <div className="col-2" key={gn}>
                        <div className="mega-menu-item menu-lv-2">
                          <p className="menu-heading">{gn === "All" ? cat.name : gn}</p>
                          <ul className="sub-menu_list">
                            {groups[gn].map((sub) => (
                              <li key={sub.id}>
                                <Link to={subLink(sub)} className="sub-menu_link has-text">
                                  <span className="cus-text">{sub.name}</span>
                                </Link>
                              </li>
                            ))}
                          </ul>
                        </div>
                      </div>
                    ))}

                    {/* Nav products — product cards after subcategories */}
                    {cat.nav_products && cat.nav_products.length > 0 && (
                      <div className="col-4 d-none d-lg-block">
                        <p className="menu-heading mb-12">Featured</p>
                        <div style={{ display: "flex", gap: 12 }}>
                          {cat.nav_products.slice(0, 4).map((prod) => (
                            <Link
                              key={prod.id}
                              to={`/product-detail/${prod.id}`}
                              style={{ flex: 1, textDecoration: "none", color: "inherit", minWidth: 0 }}
                            >
                              <img
                                src={prod.thumbnail_url || apiImageUrl(prod.thumbnail)}
                                alt={prod.name}
                                style={{
                                  width: "100%",
                                  aspectRatio: "3/4",
                                  objectFit: "cover",
                                  borderRadius: 6,
                                  display: "block",
                                  marginBottom: 8,
                                }}
                              />
                              <span
                                className="text-line-clamp-2 d-block"
                                style={{ fontSize: 12, fontWeight: 500, color: "#111", marginBottom: 4, lineHeight: 1.4 }}
                              >
                                {prod.name}
                              </span>
                              <div>
                                <span style={{ fontSize: 12, fontWeight: 700, color: "#f59e0b" }}>
                                  {formatPrice(Number(prod.sale_price ?? prod.price))}
                                </span>
                                {prod.sale_price != null && (
                                  <span style={{ fontSize: 11, color: "#999", textDecoration: "line-through", marginLeft: 5 }}>
                                    {formatPrice(Number(prod.price))}
                                  </span>
                                )}
                              </div>
                            </Link>
                          ))}
                        </div>
                      </div>
                    )}

                  </div>
                </div>
              </div>
            )}
          </li>
        );
      })}
    </>
  );
}
