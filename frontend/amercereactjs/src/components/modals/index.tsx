import {
  Suspense,
  lazy,
  useCallback,
  useEffect,
  useRef,
  type ReactNode,
} from "react";
import { useLocation } from "react-router-dom";

const Ask = lazy(() => import("@/components/modals/Ask"));
const ForgotPass = lazy(() => import("@/components/modals/ForgotPass"));
const QuickAdd = lazy(() => import("@/components/modals/QuickAdd"));
const Register = lazy(() => import("@/components/modals/Register"));
const Search = lazy(() => import("@/components/modals/Search"));
const Share = lazy(() => import("@/components/modals/Share"));
const SignIn = lazy(() => import("@/components/modals/SignIn"));
const SizeGuide = lazy(() => import("@/components/modals/SizeGuide"));
const Cart = lazy(() => import("@/components/modals/Cart"));
const Compare = lazy(() => import("@/components/modals/Compare"));
const MobileMenu = lazy(() => import("@/components/modals/MobileMenu"));
const QuickView = lazy(() => import("@/components/modals/QuickView"));
const OrderDetails = lazy(() => import("@/components/modals/OrderDetails"));
const Toolbar         = lazy(() => import("@/components/modals/Toolbar"));
const PhoneOTP        = lazy(() => import("@/components/modals/PhoneOTP"));
const ProductViewModal = lazy(() => import("@/components/modals/ProductViewModal"));

type BootstrapModalInstance = {
  hide: () => void;
};

type BootstrapOffcanvasInstance = {
  hide: () => void;
};

type BootstrapStatic = {
  Modal: {
    getOrCreateInstance(element: HTMLElement): BootstrapModalInstance;
    getInstance(element: HTMLElement): BootstrapModalInstance | null;
  };
  Offcanvas: {
    getOrCreateInstance(element: HTMLElement): BootstrapOffcanvasInstance;
    getInstance(element: HTMLElement): BootstrapOffcanvasInstance | null;
  };
};

function LazyModal({ children }: { children: ReactNode }) {
  return <Suspense fallback={null}>{children}</Suspense>;
}

export default function LayoutModals() {
  const { pathname } = useLocation();
  const modalElementsRef = useRef<HTMLElement[]>([]);
  const offcanvasElementsRef = useRef<HTMLElement[]>([]);
  const modalInstancesRef = useRef<BootstrapModalInstance[]>([]);
  const offcanvasInstancesRef = useRef<BootstrapOffcanvasInstance[]>([]);

  const registerModalElement = useCallback((el: HTMLElement | null) => {
    if (!el) return;
    if (!modalElementsRef.current.includes(el)) {
      modalElementsRef.current.push(el);
    }
  }, []);

  const registerOffcanvasElement = useCallback((el: HTMLElement | null) => {
    if (!el) return;
    if (!offcanvasElementsRef.current.includes(el)) {
      offcanvasElementsRef.current.push(el);
    }
  }, []);

  useEffect(() => {
    let isCancelled = false;

    async function collectAndCloseModals() {
      if (typeof window === "undefined") return;

      const bootstrapModule =
        (await import("bootstrap")) as unknown as BootstrapStatic;
      if (isCancelled) return;

      const { Modal, Offcanvas } = bootstrapModule;

      modalInstancesRef.current = modalElementsRef.current
        .map((el) => Modal.getInstance(el) ?? Modal.getOrCreateInstance(el))
        .filter(
          (instance): instance is BootstrapModalInstance => instance != null,
        );
      modalInstancesRef.current.forEach((instance) => {
        try {
          instance.hide();
        } catch (error) {
          console.log("Error hiding modal:", error);
        }
      });

      offcanvasInstancesRef.current = offcanvasElementsRef.current
        .map(
          (el) =>
            Offcanvas.getInstance(el) ?? Offcanvas.getOrCreateInstance(el),
        )
        .filter(
          (instance): instance is BootstrapOffcanvasInstance =>
            instance != null,
        );
      offcanvasInstancesRef.current.forEach((instance) => {
        try {
          instance.hide();
        } catch (error) {
          console.log("Error hiding offcanvas:", error);
        }
      });
    }

    collectAndCloseModals();

    return () => {
      isCancelled = true;
    };
  }, [pathname]);

  return (
    <>
      <LazyModal>
        <Ask registerModalElement={registerModalElement} />
      </LazyModal>
      <LazyModal>
        <ForgotPass />
      </LazyModal>
      <LazyModal>
        <OrderDetails registerModalElement={registerModalElement} />
      </LazyModal>
      <LazyModal>
        <QuickAdd />
      </LazyModal>
      <LazyModal>
        <Register />
      </LazyModal>
      <LazyModal>
        <Search registerModalElement={registerModalElement} />
      </LazyModal>
      <LazyModal>
        <Share registerModalElement={registerModalElement} />
      </LazyModal>
      <LazyModal>
        <SignIn />
      </LazyModal>
      <LazyModal>
        <SizeGuide registerModalElement={registerModalElement} />
      </LazyModal>
      <LazyModal>
        <Cart />
      </LazyModal>
      <LazyModal>
        <Compare registerOffcanvasElement={registerOffcanvasElement} />
      </LazyModal>
      <LazyModal>
        <MobileMenu registerOffcanvasElement={registerOffcanvasElement} />
      </LazyModal>
      <LazyModal>
        <QuickView />
      </LazyModal>

      <LazyModal>
        <Toolbar />
      </LazyModal>
      <LazyModal>
        <PhoneOTP />
      </LazyModal>
      <LazyModal>
        <ProductViewModal />
      </LazyModal>
    </>
  );
}
