import "@/styles/globals.scss";

import {
  Suspense,
  lazy,
  useEffect,
  type ComponentType,
  type LazyExoticComponent,
} from "react";
import { BrowserRouter, Navigate, Route, Routes, useLocation } from "react-router-dom";
import { useAuthStore } from "@/store/authStore";
import { useModalStore } from "@/store/modalStore";
import { useWishlistStore } from "@/store/wishlistStore";
import { rememberAuthReturn } from "@/utils/authRedirect";
import { applyPaidCartClearFromStorage, syncCartFromServer } from "@/utils/cartSync";
import { useStore } from "@/context/store";

import AccountLayout from "@/pages/account/layout";
import BlogsLayout from "@/pages/blogs/layout";
import PagesLayout from "@/pages/pages/layout";
import ShopDetailsLayout from "@/pages/shop-details/layout";
import ShopLayout from "@/pages/shop/layout";
import ScrollToTop from "./components/common/ScrollToTop";
import SiteScripts from "./components/common/SiteScripts";
import LayoutModals from "./components/modals";

const routeSuspenseFallback = (
  <div className="preload preload-container" id="preload">
    <div className="preload-logo">
      <div className="spinner" />
    </div>
  </div>
);

/** Route targets include legacy async/Next-style page modules; widen for lazy entries only. */
// eslint-disable-next-line @typescript-eslint/no-explicit-any -- lazy page modules vary (sync FC, async Next stubs)
type LazyPage = LazyExoticComponent<ComponentType<any>>;

function ShopRoute({ Page }: { Page: LazyPage }) {
  return (
    <ShopLayout>
      <Page />
    </ShopLayout>
  );
}

function AccountRoute({ Page }: { Page: LazyPage }) {
  const { isLoggedIn, token, hydrated } = useAuthStore();
  const location = useLocation();
  // Wait for persist rehydrate so we don't flash dashboard after logout
  if (!hydrated) {
    return routeSuspenseFallback;
  }
  if (!isLoggedIn || !token) {
    const ret = location.pathname + location.search;
    return <Navigate to={`/login?redirect=${encodeURIComponent(ret)}`} replace />;
  }
  return (
    <AccountLayout>
      <Page />
    </AccountLayout>
  );
}

function ShopDetailsRoute({ Page }: { Page: LazyPage }) {
  return (
    <ShopDetailsLayout>
      <Page />
    </ShopDetailsLayout>
  );
}

function BlogsRoute({ Page }: { Page: LazyPage }) {
  return (
    <BlogsLayout>
      <Page />
    </BlogsLayout>
  );
}

function PagesSectionRoute({ Page }: { Page: LazyPage }) {
  return (
    <PagesLayout>
      <Page />
    </PagesLayout>
  );
}

/** Guest hit checkout → open phone OTP box on cart (not a full login page) */
function CheckoutPhoneGate() {
  useEffect(() => {
    useModalStore.getState().openModal("signIn", { redirect: "/checkout" });
  }, []);
  return <Navigate to="/view-cart" replace />;
}

/** Wraps a ShopRoute and redirects unauthenticated users to phone OTP (checkout) or /login */
function AuthShopRoute({ Page }: { Page: LazyPage }) {
  const { isLoggedIn, token, hydrated } = useAuthStore();
  const location = useLocation();
  if (!hydrated) {
    return routeSuspenseFallback;
  }
  if (!isLoggedIn || !token) {
    const ret = location.pathname + location.search;
    if (location.pathname === "/checkout") {
      return <CheckoutPhoneGate />;
    }
    return <Navigate to={`/login?redirect=${encodeURIComponent(ret)}`} replace />;
  }
  return <ShopRoute Page={Page} />;
}

/** If session was cleared (logout / 401) while on an account URL, bounce to login */
function AuthSessionGuard() {
  const { isLoggedIn, token, hydrated } = useAuthStore();
  const location = useLocation();
  const onAccount =
    location.pathname.startsWith("/account-") ||
    location.pathname === "/wishlist" ||
    location.pathname === "/checkout";

  if (!hydrated || !onAccount) return null;
  if (!isLoggedIn || !token) {
    if (location.pathname === "/checkout") {
      return <CheckoutPhoneGate />;
    }
    return <Navigate to={`/login?redirect=${encodeURIComponent(location.pathname + location.search)}`} replace />;
  }
  return null;
}

/** Remember last browsed page so modal login returns here (cart, product, etc.) */
function AuthReturnTracker() {
  const location = useLocation();
  useEffect(() => {
    rememberAuthReturn(location.pathname + location.search);
  }, [location.pathname, location.search]);
  return null;
}

/** Loads wishlist from API on login/logout */
function WishlistSync() {
  const { isLoggedIn } = useAuthStore();
  const { fetchWishlist, clear } = useWishlistStore();
  useEffect(() => {
    if (isLoggedIn) fetchWishlist();
    else clear();
  }, [isLoggedIn]);
  return null;
}

/**
 * Pull server cart into Zustand after auth + cart persist hydrate, and when
 * the tab regains focus (so app→web adds show up without a full hard reload).
 */
function CartSync() {
  const { isLoggedIn, hydrated } = useAuthStore();
  useEffect(() => {
    if (!hydrated || !isLoggedIn) return;
    let cancelled = false;
    let focusTimer: ReturnType<typeof setTimeout> | undefined;

    const run = () => {
      if (!cancelled) {
        applyPaidCartClearFromStorage();
        void syncCartFromServer();
      }
    };

    const onCartReady = () => run();
    const unsubCart = useStore.persist.onFinishHydration(onCartReady);
    if (useStore.persist.hasHydrated()) onCartReady();

    const onFocus = () => {
      if (document.visibilityState !== "visible") return;
      clearTimeout(focusTimer);
      // Debounce so an in-flight web add can finish before we pull.
      focusTimer = setTimeout(run, 400);
    };
    window.addEventListener("focus", onFocus);
    document.addEventListener("visibilitychange", onFocus);
    return () => {
      cancelled = true;
      unsubCart();
      clearTimeout(focusTimer);
      window.removeEventListener("focus", onFocus);
      document.removeEventListener("visibilitychange", onFocus);
    };
  }, [hydrated, isLoggedIn]);
  return null;
}

/** Ensure auth persist hydration flag is set (covers edge cases) */
function AuthHydrationBootstrap() {
  useEffect(() => {
    const finish = () => useAuthStore.setState({ hydrated: true });
    const unsub = useAuthStore.persist.onFinishHydration(finish);
    if (useAuthStore.persist.hasHydrated()) finish();
    return unsub;
  }, []);
  return null;
}

/* —— Root —— */
const IndexPage = lazy(() => import("./pages/homes/home-fashion-2/index"));

/* —— Shop —— */
const ShopDefault = lazy(() => import("./pages/shop/shop-default/index"));
const ShopCheckout = lazy(() => import("./pages/shop/checkout/index"));
const ShopRegister = lazy(() => import("./pages/shop/register/index"));
const ShopSearchResult = lazy(() => import("./pages/shop/search-result/index"));
const ShopCollection = lazy(() => import("./pages/shop/collection/index"));
const ShopFilterDrawer = lazy(
  () => import("./pages/shop/shop-filter-drawer/index"),
);
const ShopHover01 = lazy(() => import("./pages/shop/shop-hover-01/index"));
const ShopHover02 = lazy(() => import("./pages/shop/shop-hover-02/index"));
const ShopHover03 = lazy(() => import("./pages/shop/shop-hover-03/index"));
const ShopHover04 = lazy(() => import("./pages/shop/shop-hover-04/index"));
const ShopHover05 = lazy(() => import("./pages/shop/shop-hover-05/index"));
const ShopHover06 = lazy(() => import("./pages/shop/shop-hover-06/index"));
const ShopFilterHidden = lazy(
  () => import("./pages/shop/shop-filter-hidden/index"),
);
const ShopFilterDropdown = lazy(
  () => import("./pages/shop/shop-filter-dropdown/index"),
);
const ShopLeftSidebar = lazy(
  () => import("./pages/shop/shop-left-sidebar/index"),
);
const ShopSubCollection = lazy(
  () => import("./pages/shop/shop-sub-collection/index"),
);
const ShopLoadMoreButton = lazy(
  () => import("./pages/shop/shop-load-more-button/index"),
);
const ShopInfinityScroll = lazy(
  () => import("./pages/shop/shop-infinity-scroll/index"),
);
const ShopRightSidebar = lazy(
  () => import("./pages/shop/shop-right-sidebar/index"),
);
const ShopFullWidth = lazy(() => import("./pages/shop/shop-full-width/index"));
const ShopFilterSidebar = lazy(
  () => import("./pages/shop/shop-filter-sidebar/index"),
);
const ShopForgetPassword = lazy(
  () => import("./pages/shop/forget-password/index"),
);
const ShopTrackOrder = lazy(() => import("./pages/shop/track-order/index"));
const ShopViewCart = lazy(() => import("./pages/shop/view-cart/index"));
const ShopWishlist = lazy(() => import("./pages/shop/wishlist/index"));
const ShopLogin = lazy(() => import("./pages/shop/login/index"));

/* —— Account —— */
const AccountAddresses = lazy(
  () => import("./pages/account/account-addresses/index"),
);
const AccountSetting = lazy(
  () => import("./pages/account/account-setting/index"),
);
const AccountPage = lazy(() => import("./pages/account/account-page/index"));
const AccountOrders = lazy(
  () => import("./pages/account/account-orders/index"),
);

/* —— Blogs —— */
const BlogIndex = lazy(() => import("./pages/blogs/blog/index"));
const BlogSingleById = lazy(() => import("./pages/blogs/blog-single/index"));

/* —— Static pages (pages/pages) —— */
const PageCompare = lazy(() => import("./pages/pages/compare/index"));
const PageAbout = lazy(() => import("./pages/pages/about/index"));
const PageContact = lazy(() => import("./pages/pages/contact/index"));
const PageReturnRefund = lazy(() => import("./pages/pages/return-refund/index"));
const Page404 = lazy(() => import("./pages/pages/404/index"));
const PageOurStore = lazy(() => import("./pages/pages/our-store/index"));
const PageInvoice = lazy(() => import("./pages/invoice/index"));
const PagePrivacyPolicy = lazy(() => import("./pages/pages/privacy-policy/index"));
const PageTermsAndConditions = lazy(() => import("./pages/pages/terms-and-conditions/index"));
const PageOrdersFaq = lazy(() => import("./pages/pages/orders-faq/index"));


/* —— Product detail variants (:id) —— */
const ProductDetail = lazy(
  () => import("./pages/shop-details/product-detail/index"),
);
const ProductRightThumbnail = lazy(
  () => import("./pages/shop-details/product-right-thumbnail/index"),
);
const ProductBottomThumbnail = lazy(
  () => import("./pages/shop-details/product-bottom-thumbnail/index"),
);
const ProductGrid = lazy(
  () => import("./pages/shop-details/product-grid/index"),
);
const ProductGrid2 = lazy(
  () => import("./pages/shop-details/product-grid-2/index"),
);
const ProductStacked = lazy(
  () => import("./pages/shop-details/product-stacked/index"),
);
const ProductDescriptionAccordion = lazy(
  () => import("./pages/shop-details/product-description-accordion/index"),
);
const ProductInnerZoom = lazy(
  () => import("./pages/shop-details/product-inner-zoom/index"),
);
const ProductInnerCircleZoom = lazy(
  () => import("./pages/shop-details/product-inner-circle-zoom/index"),
);
const ProductNoZoom = lazy(
  () => import("./pages/shop-details/product-no-zoom/index"),
);
const ProductExternalZoom = lazy(
  () => import("./pages/shop-details/product-external-zoom/index"),
);
const ProductOpenLightbox = lazy(
  () => import("./pages/shop-details/product-open-lightbox/index"),
);
const ProductVideo = lazy(
  () => import("./pages/shop-details/product-video/index"),
);
const Product3d = lazy(() => import("./pages/shop-details/product-3d/index"));
const ProductGroup = lazy(
  () => import("./pages/shop-details/product-group/index"),
);
const ProductAffiliate = lazy(
  () => import("./pages/shop-details/product-affiliate/index"),
);
const ProductOutOfStock = lazy(
  () => import("./pages/shop-details/product-out-of-stock/index"),
);
const ProductTogether = lazy(
  () => import("./pages/shop-details/product-together/index"),
);
const ProductCountdownTimer = lazy(
  () => import("./pages/shop-details/product-countdown-timer/index"),
);
const ProductVolumeDiscountThumbnail = lazy(
  () => import("./pages/shop-details/product-volume-discount-thumbnail/index"),
);
const ProductAvailable = lazy(
  () => import("./pages/shop-details/product-available/index"),
);
const ProductPreOrder = lazy(
  () => import("./pages/shop-details/product-pre-order/index"),
);
const ProductDeals = lazy(
  () => import("./pages/shop-details/product-deals/index"),
);
const ProductCustomerNote = lazy(
  () => import("./pages/shop-details/product-customer-note/index"),
);
const ProductBuyxGety = lazy(
  () => import("./pages/shop-details/product-buyx-gety/index"),
);
const ProductSwatchColor = lazy(
  () => import("./pages/shop-details/product-swatch-color/index"),
);
const ProductSwatchImage = lazy(
  () => import("./pages/shop-details/product-swatch-image/index"),
);
const ProductSwatchRounded = lazy(
  () => import("./pages/shop-details/product-swatch-rounded/index"),
);
const ProductSwatchRoundedColor = lazy(
  () => import("./pages/shop-details/product-swatch-rounded-color/index"),
);
const ProductSwatchRoundedImage = lazy(
  () => import("./pages/shop-details/product-swatch-rounded-image/index"),
);
const ProductSwatchDropdown = lazy(
  () => import("./pages/shop-details/product-swatch-dropdown/index"),
);
const ProductSwatchDropdownColor = lazy(
  () => import("./pages/shop-details/product-swatch-dropdown-color/index"),
);

// XAMPP copies live under /mcp or /deal; production (2deal.my) is document root.
function detectInstallPrefix(): string {
  if (typeof window === "undefined") return "";
  const match = window.location.pathname.match(/^\/(mcp|deal)(?=\/|$)/);
  return match ? `/${match[1]}` : "";
}

// When VITE_ROUTER_BASENAME is set (even "/" for domain root), do not fall back to VITE_BASE (/frontend/).
const routerBasename = (() => {
  const install = detectInstallPrefix();
  if (install) return install;
  const fromEnv = import.meta.env.VITE_ROUTER_BASENAME as string | undefined;
  if (fromEnv !== undefined) {
    return String(fromEnv).replace(/\/$/, "") || "/";
  }
  return import.meta.env.BASE_URL.replace(/\/$/, "") || "/";
})();

function RouteScrollToTop() {
  const location = useLocation();
  useEffect(() => {
    window.scrollTo(0, 0);
  }, [location.pathname]);
  return null;
}

function App() {
  return (
    <BrowserRouter basename={routerBasename}>
      <RouteScrollToTop />
      <Suspense fallback={routeSuspenseFallback}>
        <Routes>
          <Route path="/" element={<IndexPage />} />

          {/* Legacy / convenience redirects */}
          <Route
            path="/product-detail"
            element={<Navigate to="/product-detail/1" replace />}
          />
          <Route
            path="/product-deals"
            element={<Navigate to="/product-deals/1" replace />}
          />
          <Route
            path="/blog-single"
            element={<Navigate to="/blog" replace />}
          />

          {/* Shop */}
          <Route
            path="/shop-default"
            element={<ShopRoute Page={ShopDefault} />}
          />
          <Route
            path="/shop-left-sidebar"
            element={<ShopRoute Page={ShopLeftSidebar} />}
          />
          <Route
            path="/shop-right-sidebar"
            element={<ShopRoute Page={ShopRightSidebar} />}
          />
          <Route
            path="/shop-full-width"
            element={<ShopRoute Page={ShopFullWidth} />}
          />
          <Route
            path="/collection"
            element={<ShopRoute Page={ShopCollection} />}
          />
          <Route
            path="/shop-sub-collection"
            element={<ShopRoute Page={ShopSubCollection} />}
          />
          <Route
            path="/shop-load-more-button"
            element={<ShopRoute Page={ShopLoadMoreButton} />}
          />
          <Route
            path="/shop-infinity-scroll"
            element={<ShopRoute Page={ShopInfinityScroll} />}
          />
          <Route
            path="/shop-filter-sidebar"
            element={<ShopRoute Page={ShopFilterSidebar} />}
          />
          <Route
            path="/shop-filter-hidden"
            element={<ShopRoute Page={ShopFilterHidden} />}
          />
          <Route
            path="/shop-filter-dropdown"
            element={<ShopRoute Page={ShopFilterDropdown} />}
          />
          <Route
            path="/shop-filter-drawer"
            element={<ShopRoute Page={ShopFilterDrawer} />}
          />
          <Route
            path="/shop-hover-01"
            element={<ShopRoute Page={ShopHover01} />}
          />
          <Route
            path="/shop-hover-02"
            element={<ShopRoute Page={ShopHover02} />}
          />
          <Route
            path="/shop-hover-03"
            element={<ShopRoute Page={ShopHover03} />}
          />
          <Route
            path="/shop-hover-04"
            element={<ShopRoute Page={ShopHover04} />}
          />
          <Route
            path="/shop-hover-05"
            element={<ShopRoute Page={ShopHover05} />}
          />
          <Route
            path="/shop-hover-06"
            element={<ShopRoute Page={ShopHover06} />}
          />
          <Route path="/wishlist" element={<AuthShopRoute Page={ShopWishlist} />} />
          <Route
            path="/search-result"
            element={<ShopRoute Page={ShopSearchResult} />}
          />
          <Route
            path="/view-cart"
            element={<ShopRoute Page={ShopViewCart} />}
          />
          <Route path="/login" element={<ShopRoute Page={ShopLogin} />} />
          <Route path="/register" element={<ShopRoute Page={ShopRegister} />} />
          <Route
            path="/forget-password"
            element={<ShopRoute Page={ShopForgetPassword} />}
          />
          <Route
            path="/track-order"
            element={<ShopRoute Page={ShopTrackOrder} />}
          />
          <Route path="/checkout" element={<AuthShopRoute Page={ShopCheckout} />} />

          {/* Account */}
          <Route
            path="/account-page"
            element={<AccountRoute Page={AccountPage} />}
          />
          <Route
            path="/account-orders"
            element={<AccountRoute Page={AccountOrders} />}
          />
          <Route
            path="/account-addresses"
            element={<AccountRoute Page={AccountAddresses} />}
          />
          <Route
            path="/account-setting"
            element={<AccountRoute Page={AccountSetting} />}
          />
          <Route
            path="/account-wallet"
            element={<Navigate to="/account-orders" replace />}
          />
          <Route
            path="/account-wallet/topup"
            element={<Navigate to="/account-orders" replace />}
          />

          {/* Blog */}
          <Route path="/blog" element={<BlogsRoute Page={BlogIndex} />} />
          <Route
            path="/blog-single/:id"
            element={<BlogsRoute Page={BlogSingleById} />}
          />

          {/* Marketing / utility pages */}
          <Route
            path="/about"
            element={<PagesSectionRoute Page={PageAbout} />}
          />
          <Route
            path="/contact"
            element={<PagesSectionRoute Page={PageContact} />}
          />
          <Route
            path="/return-refund"
            element={<PagesSectionRoute Page={PageReturnRefund} />}
          />
          <Route
            path="/orders-faq"
            element={<PagesSectionRoute Page={PageOrdersFaq} />}
          />
          <Route
            path="/our-store"
            element={<PagesSectionRoute Page={PageOurStore} />}
          />
          <Route path="/404" element={<PagesSectionRoute Page={Page404} />} />
          <Route
            path="/compare"
            element={<PagesSectionRoute Page={PageCompare} />}
          />
          <Route
            path="/invoice"
            element={<PagesSectionRoute Page={PageInvoice} />}
          />
          <Route
            path="/privacy-policy"
            element={<PagesSectionRoute Page={PagePrivacyPolicy} />}
          />
          <Route
            path="/terms-and-conditions"
            element={<PagesSectionRoute Page={PageTermsAndConditions} />}
          />


          {/* Product pages */}
          <Route
            path="/product-detail/:id"
            element={<ShopDetailsRoute Page={ProductDetail} />}
          />
          <Route
            path="/product-right-thumbnail/:id"
            element={<ShopDetailsRoute Page={ProductRightThumbnail} />}
          />
          <Route
            path="/product-bottom-thumbnail/:id"
            element={<ShopDetailsRoute Page={ProductBottomThumbnail} />}
          />
          <Route
            path="/product-grid/:id"
            element={<ShopDetailsRoute Page={ProductGrid} />}
          />
          <Route
            path="/product-grid-2/:id"
            element={<ShopDetailsRoute Page={ProductGrid2} />}
          />
          <Route
            path="/product-stacked/:id"
            element={<ShopDetailsRoute Page={ProductStacked} />}
          />
          <Route
            path="/product-description-accordion/:id"
            element={<ShopDetailsRoute Page={ProductDescriptionAccordion} />}
          />
          <Route
            path="/product-inner-zoom/:id"
            element={<ShopDetailsRoute Page={ProductInnerZoom} />}
          />
          <Route
            path="/product-inner-circle-zoom/:id"
            element={<ShopDetailsRoute Page={ProductInnerCircleZoom} />}
          />
          <Route
            path="/product-no-zoom/:id"
            element={<ShopDetailsRoute Page={ProductNoZoom} />}
          />
          <Route
            path="/product-external-zoom/:id"
            element={<ShopDetailsRoute Page={ProductExternalZoom} />}
          />
          <Route
            path="/product-open-lightbox/:id"
            element={<ShopDetailsRoute Page={ProductOpenLightbox} />}
          />
          <Route
            path="/product-video/:id"
            element={<ShopDetailsRoute Page={ProductVideo} />}
          />
          <Route
            path="/product-3d/:id"
            element={<ShopDetailsRoute Page={Product3d} />}
          />
          <Route
            path="/product-group/:id"
            element={<ShopDetailsRoute Page={ProductGroup} />}
          />
          <Route
            path="/product-affiliate/:id"
            element={<ShopDetailsRoute Page={ProductAffiliate} />}
          />
          <Route
            path="/product-out-of-stock/:id"
            element={<ShopDetailsRoute Page={ProductOutOfStock} />}
          />
          <Route
            path="/product-together/:id"
            element={<ShopDetailsRoute Page={ProductTogether} />}
          />
          <Route
            path="/product-countdown-timer/:id"
            element={<ShopDetailsRoute Page={ProductCountdownTimer} />}
          />
          <Route
            path="/product-volume-discount-thumbnail/:id"
            element={<ShopDetailsRoute Page={ProductVolumeDiscountThumbnail} />}
          />
          <Route
            path="/product-available/:id"
            element={<ShopDetailsRoute Page={ProductAvailable} />}
          />
          <Route
            path="/product-pre-order/:id"
            element={<ShopDetailsRoute Page={ProductPreOrder} />}
          />
          <Route
            path="/product-deals/:id"
            element={<ShopDetailsRoute Page={ProductDeals} />}
          />
          <Route
            path="/product-customer-note/:id"
            element={<ShopDetailsRoute Page={ProductCustomerNote} />}
          />
          <Route
            path="/product-buyx-gety/:id"
            element={<ShopDetailsRoute Page={ProductBuyxGety} />}
          />
          <Route
            path="/product-swatch-color/:id"
            element={<ShopDetailsRoute Page={ProductSwatchColor} />}
          />
          <Route
            path="/product-swatch-image/:id"
            element={<ShopDetailsRoute Page={ProductSwatchImage} />}
          />
          <Route
            path="/product-swatch-rounded/:id"
            element={<ShopDetailsRoute Page={ProductSwatchRounded} />}
          />
          <Route
            path="/product-swatch-rounded-color/:id"
            element={<ShopDetailsRoute Page={ProductSwatchRoundedColor} />}
          />
          <Route
            path="/product-swatch-rounded-image/:id"
            element={<ShopDetailsRoute Page={ProductSwatchRoundedImage} />}
          />
          <Route
            path="/product-swatch-dropdown/:id"
            element={<ShopDetailsRoute Page={ProductSwatchDropdown} />}
          />
          <Route
            path="/product-swatch-dropdown-color/:id"
            element={<ShopDetailsRoute Page={ProductSwatchDropdownColor} />}
          />

          <Route path="*" element={<PagesSectionRoute Page={Page404} />} />
        </Routes>
      </Suspense>
      <AuthHydrationBootstrap />
      <AuthReturnTracker />
      <AuthSessionGuard />
      <WishlistSync />
      <CartSync />
      <LayoutModals />
      <SiteScripts />
      <ScrollToTop />
    </BrowserRouter>
  );
}

export default App;
