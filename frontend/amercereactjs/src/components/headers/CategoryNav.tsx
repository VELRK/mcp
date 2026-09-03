import { Link } from "react-router-dom";
import { useCategories, useProducts, apiImageUrl } from "@/hooks/useApi";
import type { ApiCategory } from "@/services/api";
import { formatPrice } from "@/utils/formatPrice";

function catLink(c: ApiCategory) {
  return `/shop-default?category_id=${c.id}`;
}
function subLink(s: ApiCategory) {
  return `/shop-default?subcategory_id=${s.id}`;
}

export default function CategoryNav() {
  const { categories } = useCategories();
  const { products: featuredProducts } = useProducts({ limit: 2 }); // Get 2 featured products for the side panel

  if (!categories.length) return null;

  return (
    <>
      <style>{`
        .menu-item {
          position: static !important; /* Allow mega menu to be relative to the nav/header */
        }
        .menu-item:hover > .mega-menu {
          opacity: 1;
          visibility: visible;
          transform: translateY(0);
          pointer-events: auto;
        }
        .mega-menu {
          position: absolute;
          top: 100%;
          left: 0;
          width: 100vw;
          margin-left: calc(-50vw + 50%);
          background: #ffffff;
          box-shadow: 0 30px 60px rgba(0, 0, 0, 0.12);
          padding: 50px 0;
          display: flex;
          justify-content: center;
          opacity: 0;
          visibility: hidden;
          pointer-events: none;
          transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
          z-index: 1001;
          border-top: 1px solid #f1f1f1;
          transform: translateY(10px);
        }
        .mega-container {
          width: 100%;
          max-width: 1400px;
          margin: 0 auto;
          display: flex;
          padding: 0 40px;
          gap: 60px;
        }
        .mega-left {
          flex: 1;
          display: flex;
          flex-wrap: wrap;
          gap: 30px;
        }
        .mega-right {
          width: 420px;
          display: flex;
          gap: 25px;
          border-left: 1px solid #eee;
          padding-left: 50px;
        }
        .mega-col { 
          flex: 0 0 calc(25% - 25px); 
          min-width: 140px; 
        }
        .mega-title { 
          font-weight: 700; 
          font-size: 13px; 
          color: #000; 
          margin-bottom: 22px; 
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }
        .mega-list { 
          list-style: none; 
          padding: 0; 
          margin: 0; 
        }
        .mega-list li { 
          margin-bottom: 12px; 
        }
        .mega-list a { 
          font-size: 14px; 
          color: #555; 
          text-decoration: none; 
          transition: all 0.2s;
          display: block;
          line-height: 1.4;
        }
        .mega-list a:hover { 
          color: #f59e0b; 
          padding-left: 5px;
        }
        .item-link {
          padding: 25px 15px;
          display: flex;
          align-items: center;
          gap: 6px;
          color: #111;
          text-decoration: none;
          position: relative;
          font-weight: 500;
        }
        .item-link:after {
          content: "";
          position: absolute;
          bottom: 22px;
          left: 15px;
          right: 15px;
          height: 2px;
          background: #f59e0b;
          transform: scaleX(0);
          transition: transform 0.3s;
        }
        .item-link:hover:after {
          transform: scaleX(1);
        }
        .item-link:hover {
          color: #f59e0b;
        }
        .item-link .text {
          font-size: 14px;
        }

        /* Product Card */
        .mega-prod-card {
          flex: 1;
          text-decoration: none;
          color: inherit;
          transition: opacity 0.2s;
        }
        .mega-prod-card:hover {
          opacity: 0.8;
        }
        .mega-prod-img {
          width: 100%;
          aspect-ratio: 3/4;
          object-fit: cover;
          border-radius: 4px;
          margin-bottom: 15px;
        }
        .mega-prod-name {
          font-size: 14px;
          font-weight: 500;
          margin-bottom: 6px;
          color: #111;
          display: block;
        }
        .mega-prod-price {
          font-size: 14px;
          font-weight: 700;
          color: #f59e0b;
        }
        .mega-prod-old {
          font-size: 12px;
          color: #999;
          text-decoration: line-through;
          margin-left: 8px;
        }
      `}</style>

      {categories.map((cat) => {
        const hasSub = cat.children && cat.children.length > 0;
        
        const groups: Record<string, ApiCategory[]> = {};
        if (hasSub) {
          cat.children!.forEach(sub => {
            const g = sub.mega_group || "POPULAR"; 
            if (!groups[g]) groups[g] = [];
            groups[g].push(sub);
          });
        }

        const groupNames = Object.keys(groups);

        return (
          <li key={cat.id} className="menu-item position-relative">
            <Link to={catLink(cat)} className="item-link">
              <span className="text">{cat.name}</span>
              {hasSub && <i className="icon icon-CaretDown" style={{ fontSize: 10 }} aria-hidden />}
            </Link>

            {hasSub && (
              <div className="mega-menu">
                <div className="mega-container">
                  <div className="mega-left">
                    {groupNames.map(gn => (
                      <div key={gn} className="mega-col">
                        <div className="mega-title">{gn}</div>
                        <ul className="mega-list">
                          {groups[gn].map(sub => (
                            <li key={sub.id}>
                              <Link to={subLink(sub)}>{sub.name}</Link>
                            </li>
                          ))}
                        </ul>
                      </div>
                    ))}
                  </div>
                  
                  {/* Right Panel with Featured Products */}
                  <div className="mega-right d-none d-lg-flex">
                    {featuredProducts.map(prod => (
                      <Link key={prod.id} to={`/product-detail/${prod.id}`} className="mega-prod-card">
                        <img src={apiImageUrl(prod.thumbnail)} alt={prod.name} className="mega-prod-img" />
                        <span className="mega-prod-name text-line-clamp-1">{prod.name}</span>
                        <div className="d-flex align-items-center gap-1">
                          {[1,2,3,4,5].map(s => (
                            <i key={s} className="bi bi-star-fill text-warning" style={{ fontSize: 9 }}></i>
                          ))}
                        </div>
                        <div className="mt-1">
                          <span className="mega-prod-price">{formatPrice(Number(prod.sale_price || prod.price))}</span>
                          {prod.sale_price && <span className="mega-prod-old">{formatPrice(Number(prod.price))}</span>}
                        </div>
                      </Link>
                    ))}
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
