import { useNavigate } from "react-router-dom";
import { useState, useRef, useCallback } from "react";
import { useCategories } from "@/hooks/useApi";
import type { ApiCategory } from "@/services/api";
import { useAuthStore } from "@/store/authStore";
import { useModalStore } from "@/store/modalStore";
function catUrl(c: ApiCategory) { return `/shop-default?category_id=${c.id}`; }
function subUrl(s: ApiCategory) { return `/shop-default?subcategory_id=${s.id}`; }

export default function MobileMenu({
  registerOffcanvasElement,
}: {
  registerOffcanvasElement?: (el: HTMLElement | null) => void;
}) {
  const navigate = useNavigate();
  const elRef = useRef<HTMLDivElement | null>(null);
  const { categories } = useCategories();
  const { isLoggedIn } = useAuthStore();
  const { openModal } = useModalStore();

  const [search, setSearch] = useState("");
  const [openCats, setOpenCats] = useState<Set<number>>(new Set());

  // Imperatively hide the offcanvas, then navigate
  const closeAndGo = useCallback((path: string) => {
    const el = elRef.current;
    if (el) {
      import("bootstrap").then(({ Offcanvas }) => {
        Offcanvas.getInstance(el)?.hide();
      }).catch(() => { });
    }
    navigate(path);
  }, [navigate]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const q = search.trim();
    closeAndGo(q ? `/shop-default?q=${encodeURIComponent(q)}` : "/shop-default");
    setSearch("");
  };

  const toggleCat = (id: number) => {
    setOpenCats((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };

  const refCb = useCallback((el: HTMLDivElement | null) => {
    elRef.current = el;
    registerOffcanvasElement?.(el);
  }, [registerOffcanvasElement]);

  return (
    <div ref={refCb} className="offcanvas offcanvas-start" id="mobileMenu" style={{ width: "320px", borderRight: "none", boxShadow: "5px 0 25px rgba(0,0,0,0.1)" }}>
      {/* Premium Header */}
      <div className="d-flex justify-content-between align-items-center p-4 border-bottom">
        <h5 className="m-0 fw-bold" style={{ letterSpacing: "0.5px" }}>Menu</h5>
        <button type="button" className="btn-close shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>

      {/* Search Bar */}
      <div className="p-3 bg-light border-bottom">
        <form onSubmit={handleSearch} className="position-relative">
          <input
            type="text"
            className="form-control rounded-pill border-0 shadow-sm"
            placeholder="Search for items..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            style={{ paddingLeft: "40px", height: "45px", fontSize: "0.95rem" }}
          />
          <i className="icon icon-MagnifyingGlass position-absolute" style={{ left: "15px", top: "50%", transform: "translateY(-50%)", color: "#888", fontSize: "1.1rem" }} />
        </form>
      </div>

      {/* Categories Body */}
      <div className="offcanvas-body p-0 d-flex flex-column" style={{ overflowX: "hidden", overflowY: "auto" }}>
        <ul className="list-unstyled m-0 w-100 flex-grow-1">
          <li className="border-bottom">
            <button
              onClick={() => closeAndGo("/")}
              className="w-100 d-flex align-items-center px-4 py-3 bg-white text-dark border-0"
              style={{ fontSize: "1rem", fontWeight: 500 }}
            >
              <i className="icon icon-House me-3 fs-5 text-muted"></i>
              Home
            </button>
          </li>

          {categories.map((cat) => {
            const hasSub = (cat.children?.length ?? 0) > 0;
            const isOpen = openCats.has(cat.id);

            return (
              <li key={cat.id} className="border-bottom">
                {hasSub ? (
                  <>
                    <button
                      type="button"
                      className="w-100 d-flex align-items-center justify-content-between px-4 py-3 bg-white border-0 text-dark"
                      onClick={() => toggleCat(cat.id)}
                      style={{ fontSize: "1rem", fontWeight: 500, transition: "background 0.2s" }}
                    >
                      <span className="d-flex align-items-center">
                        <i className="icon icon-Storefront me-3 fs-5 text-muted"></i>
                        {cat.name}
                      </span>
                      <i className={`icon icon-Caret${isOpen ? "Up" : "Down"} text-muted`} style={{ fontSize: "0.9rem", transition: "transform 0.3s" }}></i>
                    </button>
                    {/* Subcategories Container */}
                    <div
                      style={{
                        maxHeight: isOpen ? "800px" : "0",
                        overflow: "hidden",
                        transition: "max-height 0.3s ease-in-out",
                        backgroundColor: "#f9f9f9"
                      }}
                    >
                      <ul className="list-unstyled m-0 py-2">
                        <li>
                          <button
                            type="button"
                            className="w-100 text-start px-5 py-2 border-0 bg-transparent text-dark"
                            onClick={() => closeAndGo(catUrl(cat))}
                            style={{ fontSize: "0.95rem" }}
                          >
                            <span className="fw-medium text-primary">View All {cat.name}</span>
                          </button>
                        </li>
                        {cat.children!.map((sub) => (
                          <li key={sub.id}>
                            <button
                              type="button"
                              className="w-100 text-start px-5 py-2 border-0 bg-transparent text-secondary"
                              onClick={() => closeAndGo(subUrl(sub))}
                              style={{ fontSize: "0.95rem" }}
                              onMouseEnter={(e) => e.currentTarget.classList.replace("text-secondary", "text-dark")}
                              onMouseLeave={(e) => e.currentTarget.classList.replace("text-dark", "text-secondary")}
                            >
                              {sub.name}
                            </button>
                          </li>
                        ))}
                      </ul>
                    </div>
                  </>
                ) : (
                  <button
                    type="button"
                    className="w-100 d-flex align-items-center px-4 py-3 bg-white text-dark border-0"
                    onClick={() => closeAndGo(catUrl(cat))}
                    style={{ fontSize: "1rem", fontWeight: 500 }}
                  >
                    <i className="icon icon-Storefront me-3 fs-5 text-muted"></i>
                    {cat.name}
                  </button>
                )}
              </li>
            );
          })}
        </ul>

        {/* Footer Actions */}
        <div className="bg-light p-4 mt-auto border-top">
          <ul className="list-unstyled d-flex flex-column gap-3 m-0">
            <li>
              <button
                onClick={() => {
                  if (!isLoggedIn) {
                    const el = elRef.current;
                    if (el) {
                      import("bootstrap").then(({ Offcanvas }) => {
                        Offcanvas.getInstance(el)?.hide();
                        setTimeout(() => openModal("signIn"), 300);
                      }).catch(() => { });
                    }
                  } else {
                    closeAndGo("/wishlist");
                  }
                }}
                className="w-100 d-flex align-items-center border-0 bg-transparent p-0 text-dark"
              >
                <i className="icon icon-HeartStraight fs-4 me-3 text-muted"></i>
                <span className="fw-medium">Wishlist</span>
              </button>
            </li>
            <li>
              {isLoggedIn ? (
                <button
                  onClick={() => closeAndGo("/account-page")}
                  className="w-100 d-flex align-items-center border-0 bg-transparent p-0 text-dark"
                >
                  <i className="icon icon-User fs-4 me-3 text-muted"></i>
                  <span className="fw-medium">My Account</span>
                </button>
              ) : (
                <button
                  onClick={() => {
                    const el = elRef.current;
                    if (el) {
                      import("bootstrap").then(({ Offcanvas }) => {
                        Offcanvas.getInstance(el)?.hide();
                        setTimeout(() => openModal("signIn"), 300);
                      }).catch(() => { });
                    }
                  }}
                  className="w-100 d-flex align-items-center border-0 bg-transparent p-0 text-dark"
                >
                  <i className="icon icon-User fs-4 me-3 text-muted"></i>
                  <span className="fw-medium">Account</span>
                </button>
              )}
            </li>
          </ul>

          <div className="mt-4 pt-4 border-top text-center">
            <p className="text-muted mb-1 fs-6">Need Help?</p>
            <a href="mailto:golden2deal@gmail.com" className="fw-medium text-dark text-decoration-none">
              golden2deal@gmail.com
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}
