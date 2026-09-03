import { Link } from "react-router-dom";
import CartIconCount from "./CartIconCount";
import AccountIcon from "./AccountIcon";
import Nav from "./Nav";
import { useHeaderSticky } from "@/hooks/useHeaderSticky";
import { useModalStore } from "@/store/modalStore";
import { useAuthStore } from "@/store/authStore";

export default function Header1() {
  const headerSticky = useHeaderSticky();

  return (
    <header
      className={`tf-header header-s2${headerSticky ? " header-sticky" : ""}`}
      style={{
        top: headerSticky ? "0px" : "-200px",
        transition: "top 0.4s cubic-bezier(0.25, 0.8, 0.25, 1), background-color 0.3s ease",
        backgroundColor: headerSticky ? "rgba(255, 255, 255, 0.95)" : "#ffffff",
        backdropFilter: headerSticky ? "blur(10px)" : "none",
        borderBottom: "1px solid rgba(0,0,0,0.04)",
        boxShadow: headerSticky ? "0 4px 24px rgba(0,0,0,0.04)" : "none",
        zIndex: 1000
      }}
    >
      <div className="container-full px-4 px-xl-5">
        <div className="header-inner d-flex align-items-center justify-content-between" style={{ padding: "0", minHeight: "85px" }}>

          {/* Left section: Hamburger (mobile) + Logo + Nav (desktop) */}
          <div className="header-left d-flex align-items-center gap-3 gap-xl-4">
            {/* Mobile hamburger */}
            <div className="box-open-menu-mobile d-xl-none">
              <a href="#mobileMenu" data-bs-toggle="offcanvas" className="btn-open-menu" style={{ color: "#222" }}>
                <i className="icon icon-List fs-4" />
              </a>
            </div>

            <Link to="/" className="logo-site flex-shrink-0">
              <img
                loading="lazy"
                src="/frontend/assets/logo/logo.png"
                alt="2Deal"
                style={{
                  width: "80px",
                  height: "80px",
                  objectFit: "contain",
                  borderRadius: "12px",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.1)",
                  padding: "4px",
                  backgroundColor: "#fff",
                  border: "1px solid #eaeaea",
                  transition: "transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)",
                }}
                onMouseEnter={(e) => e.currentTarget.style.transform = "scale(1.05)"}
                onMouseLeave={(e) => e.currentTarget.style.transform = "scale(1)"}
              />
            </Link>

            {/* Divider */}
            <span
              className="d-none d-xl-block flex-shrink-0"
              style={{ width: "1px", height: "32px", background: "rgba(0,0,0,0.06)", margin: "0 10px" }}
            />

            <nav className="box-navigation d-none d-xl-block">
              <ul className="box-nav-menu d-flex align-items-center m-0 p-0" style={{ gap: "35px" }}>
                <Nav />
              </ul>
            </nav>
          </div>

          {/* Right icons */}
          <div className="header-right">
            <ul className="nav-icon-list d-flex align-items-center m-0 p-0" style={{ gap: "25px", listStyle: "none" }}>
              <li className="d-none d-sm-block">
                <a href="#search" data-bs-toggle="modal" className="nav-icon-item link" style={{ color: "#222", transition: "color 0.2s" }} onMouseEnter={(e) => e.currentTarget.style.color = "#777"} onMouseLeave={(e) => e.currentTarget.style.color = "#222"}>
                  <i className="icon icon-MagnifyingGlass fs-5" />
                </a>
              </li>
              <li>
                <div style={{ color: "#222", transition: "color 0.2s", cursor: "pointer" }} onMouseEnter={(e) => e.currentTarget.style.color = "#777"} onMouseLeave={(e) => e.currentTarget.style.color = "#222"}>
                  <AccountIcon />
                </div>
              </li>
              <li className="d-none d-sm-block">
                <Link
                  to="/wishlist"
                  onClick={(e) => {
                    if (!useAuthStore.getState().isLoggedIn) {
                      e.preventDefault();
                      useModalStore.getState().openModal("signIn");
                    }
                  }}
                  className="nav-icon-item link"
                  style={{ color: "#222", transition: "color 0.2s" }}
                  onMouseEnter={(e) => e.currentTarget.style.color = "#777"}
                  onMouseLeave={(e) => e.currentTarget.style.color = "#222"}
                >
                  <i className="icon icon-HeartStraight fs-5" />
                </Link>
              </li>
              <li>
                <button
                  type="button"
                  onClick={(e) => {
                    e.preventDefault();
                    useModalStore.getState().openModal("cart");
                  }}
                  className="nav-icon-item link shop-cart"
                  style={{
                    background: "none",
                    border: "none",
                    color: "#222",
                    transition: "color 0.2s",
                    cursor: "pointer",
                    padding: 0
                  }}
                  onMouseEnter={(e) => e.currentTarget.style.color = "#777"}
                  onMouseLeave={(e) => e.currentTarget.style.color = "#222"}
                >
                  <i className="icon icon-Handbag fs-5" />
                  <CartIconCount />
                </button>
              </li>
            </ul>
          </div>

        </div>
      </div>
    </header>
  );
}
