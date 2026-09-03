import { useEffect, useState } from "react";
import { useSiteSettings } from "@/hooks/useApi";

const messages = [
  "Free shipping on qualifying orders.",
  "Easy Enquiry or Contact us at +60126364666",
  "Order with Confidence. 100% Easy Returns Guaranteed for All Domestic Orders"
];

export default function TopBar3() {
  const { settings } = useSiteSettings();
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isAnimating, setIsAnimating] = useState(false);

  useEffect(() => {
    const interval = setInterval(() => {
      setIsAnimating(true);
      setTimeout(() => {
        setCurrentIndex((prev) => (prev + 1) % messages.length);
        setIsAnimating(false);
      }, 500);
    }, 3500);
    return () => clearInterval(interval);
  }, []);

  if (settings && settings.top_bar_enabled === false) return null;

  return (
    <div className="tf-topbar topbar-s3" style={{ backgroundColor: '#3ec1bc' }}>
      <div className="container-full">
        <div
          className="d-flex align-items-center justify-content-center"
          style={{ minHeight: 26, overflow: 'hidden', position: 'relative' }}
        >
          <p
            className="text-white text-line-clamp-1 text-center mb-0"
            style={{
              fontSize: 14,
              letterSpacing: '0.5px',
              transition: 'transform 0.5s ease, opacity 0.5s ease',
              transform: isAnimating ? 'translateY(-100%)' : 'translateY(0)',
              opacity: isAnimating ? 0 : 1,
            }}
          >
            {messages[currentIndex]}
          </p>
        </div>
      </div>
    </div>
  );
}
