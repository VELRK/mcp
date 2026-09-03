import { Link } from "react-router-dom";
import {
  footerStore,
  footerCompanyLinks,
  footerCustomerLinks,
  footerAccountLinksPage,
  footerPaymentIcons,
} from "@/data/footer";
import FooterAccordionWrapper, {
  FooterAccordionItem,
} from "./FooterAccordionWrapper";

export default function Footer9({
  parentClass = "tf-footer footer-s5",
}) {
  const formatTitle = (str: string) => {
    return str
      .split(" ")
      .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
      .join(" ");
  };

  return (
    <footer className={`${parentClass} luxury-fashion-footer`} style={{ backgroundColor: "#ECF9F8", padding: "60px 0 30px 0", fontFamily: "'Outfit', sans-serif" }}>
      <style>{`
        .luxury-fashion-footer {
          background-color: #ECF9F8 !important;
          color: #222222 !important;
          padding: 60px 0 30px 0 !important;
        }
        
        .luxury-footer-body {
          background: transparent !important;
          border: none !important;
          border-radius: 0 !important;
          position: relative;
          padding: 0 !important;
          box-shadow: none !important;
        }

        .luxury-footer-col {
          padding: 0 15px;
          margin-bottom: 30px;
        }

        .footer-brand-info {
          display: flex !important;
          flex-direction: column !important;
          align-items: flex-start !important;
        }

        .footer-logo {
          max-width: 100px !important;
          height: auto !important;
          display: block !important;
          margin-bottom: 14px !important;
          border-radius: 50% !important;
        }

        .luxury-tagline {
          font-size: 13px !important;
          color: #555555 !important;
          margin-bottom: 12px !important;
          font-weight: 400 !important;
          line-height: 1.5 !important;
        }

        .luxury-brand-contact {
          font-size: 13px !important;
          color: #555555 !important;
          margin-bottom: 22px !important;
          display: flex !important;
          flex-direction: column !important;
          gap: 10px !important;
        }

        .luxury-brand-contact-item {
          display: flex !important;
          align-items: flex-start !important;
          gap: 10px !important;
          line-height: 1.4 !important;
        }

        .luxury-brand-contact-item a {
          color: #555555 !important;
          text-decoration: none !important;
          transition: color 0.25s ease !important;
        }

        .luxury-brand-contact-item a:hover {
          color: #3ec1bc !important;
        }

        .luxury-brand-icon {
          color: #3ec1bc !important;
          font-size: 14px !important;
          flex-shrink: 0;
          margin-top: 2px;
        }

        .luxury-social-list {
          display: flex !important;
          align-items: center !important;
          gap: 10px !important;
          list-style: none !important;
          padding: 0 !important;
          margin: 0 !important;
        }

        .luxury-social-link {
          display: flex !important;
          align-items: center !important;
          justify-content: center !important;
          width: 34px !important;
          height: 34px !important;
          background-color: #3ec1bc !important;
          color: #ffffff !important;
          border-radius: 50% !important;
          font-size: 14px !important;
          transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
          text-decoration: none !important;
        }

        .luxury-social-link:hover {
          background-color: #3ec1bc !important;
          transform: translateY(-3px) !important;
          color: #ffffff !important;
          box-shadow: 0 4px 8px rgba(62, 193, 188, 0.25) !important;
        }

        .luxury-footer-heading {
          color: #111111 !important;
          font-weight: 600 !important;
          font-size: 14px !important;
          margin-bottom: 20px !important;
          text-transform: capitalize !important;
          letter-spacing: 0.05em !important;
          position: relative !important;
          display: block !important;
          width: 100% !important;
          border-bottom: none !important;
        }

        .luxury-footer-heading::after {
          display: none !important;
        }

        .luxury-footer-links {
          list-style: none !important;
          padding: 0 !important;
          margin: 0 !important;
        }

        .luxury-footer-links li {
          margin-bottom: 10px !important;
        }

        .luxury-footer-link {
          color: #555555 !important;
          font-size: 13px !important;
          text-decoration: none !important;
          transition: all 0.25s ease !important;
          display: inline-block !important;
        }

        .luxury-footer-link:hover {
          color: #3ec1bc !important;
          transform: translateX(4px) !important;
        }

        .luxury-footer-bottom {
          display: flex !important;
          flex-direction: column !important;
          align-items: center !important;
          justify-content: center !important;
          padding-top: 25px !important;
          border-top: 1px solid rgba(193, 16, 105, 0.08) !important;
          margin-top: 30px !important;
          gap: 15px !important;
        }

        .luxury-copyright {
          font-size: 12px !important;
          color: #666666 !important;
          margin: 0 !important;
          text-align: center !important;
          letter-spacing: 0.02em !important;
        }

        .luxury-payment-list {
          display: flex !important;
          align-items: center !important;
          justify-content: center !important;
          gap: 10px !important;
          list-style: none !important;
          padding: 0 !important;
          margin: 0 !important;
        }

        .luxury-payment-list img {
          opacity: 0.85 !important;
          transition: all 0.3s ease !important;
          border-radius: 3px;
          width: 38px !important;
          height: 24px !important;
        }

        .luxury-payment-list img:hover {
          opacity: 1 !important;
          transform: scale(1.08) !important;
        }

        @media (max-width: 767px) {
          .luxury-fashion-footer {
            padding: 40px 0 20px 0 !important;
          }
          .luxury-footer-col {
            margin-bottom: 0px !important;
          }
          .footer-brand-info {
            align-items: center !important;
            text-align: center !important;
            margin-bottom: 30px !important;
          }
          .luxury-social-list {
            justify-content: center !important;
          }
        }

        @media (max-width: 575px) {
          .luxury-footer-heading {
            position: relative !important;
            padding-right: 20px !important;
            margin-bottom: 0 !important;
            padding-top: 16px !important;
            padding-bottom: 16px !important;
            border-bottom: 1px solid rgba(62, 193, 188, 0.15) !important;
            color: #111111 !important;
            font-size: 15px !important;
          }

          .luxury-footer-heading::before {
            content: "+";
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #3ec1bc;
            font-size: 20px;
            transition: transform 0.3s ease;
            font-weight: 400;
          }
          
          .footer-col-block.open .luxury-footer-heading::before {
            content: "−";
            transform: translateY(-50%) rotate(180deg);
          }

          .footer-col-block .tf-collapse-content {
            display: none;
          }

          .footer-col-block.open .tf-collapse-content {
            display: block;
            padding-top: 16px !important;
            padding-bottom: 16px !important;
            border-bottom: 1px solid rgba(62, 193, 188, 0.08) !important;
          }
        }
      `}</style>

      {/* ── Main footer links ── */}
      <div className="container">
        <div className="luxury-footer-body">

          <FooterAccordionWrapper>
            <div className="row">

              {/* Brand block (Logo, Tagline, Contact Info, Socials) */}
              <div className="col-lg-3 col-md-6 luxury-footer-col">
                <div className="footer-brand-info">
                  <Link to="/" className="logo-site mb-16 d-block">
                    <img
                      loading="lazy"
                      width={100}
                      src="/frontend/assets/logo/logo.png"
                      alt="2Deal"
                      className="footer-logo"
                    />
                  </Link>
                  <p className="luxury-tagline"></p>

                  <div className="luxury-brand-contact">

                    {footerStore.phone && (
                      <div className="luxury-brand-contact-item">
                        <span className="luxury-brand-icon">📞</span>
                        <a href={footerStore.phoneHref}>
                          {footerStore.phone}
                        </a>
                      </div>
                    )}

                    <div className="luxury-brand-contact-item">
                      <span className="luxury-brand-icon">✉️</span>
                      <a href={`mailto:${footerStore.email}`}>
                        {footerStore.email}
                      </a>
                    </div>
                  </div>

                  <ul className="luxury-social-list">
                    <li>
                      <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" className="luxury-social-link">
                        <i className="icon icon-InstagramLogo" />
                      </a>
                    </li>
                    <li>
                      <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" className="luxury-social-link">
                        <i className="icon icon-FacebookLogo" />
                      </a>
                    </li>
                    <li>
                      <a href="https://x.com/" target="_blank" rel="noopener noreferrer" className="luxury-social-link">
                        <i className="icon icon-XLogo" />
                      </a>
                    </li>
                  </ul>
                </div>
              </div>

              {/* Company */}
              <div className="col-lg-3 col-md-6 luxury-footer-col">
                <FooterAccordionItem
                  id="footer9-company"
                  className="footer-col-block"
                  heading={formatTitle(footerCompanyLinks.title)}
                  headingClassName="luxury-footer-heading"
                >
                  <ul className="luxury-footer-links">
                    {footerCompanyLinks.links.map((link) => (
                      <li key={link.href + link.label}>
                        <Link to={link.href} className="luxury-footer-link">
                          {link.label}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </FooterAccordionItem>
              </div>

              {/* Customer */}
              <div className="col-lg-3 col-md-6 luxury-footer-col">
                <FooterAccordionItem
                  id="footer9-customer"
                  className="footer-col-block"
                  heading={formatTitle(footerCustomerLinks.title)}
                  headingClassName="luxury-footer-heading"
                >
                  <ul className="luxury-footer-links">
                    {footerCustomerLinks.links.map((link) => (
                      <li key={link.href + link.label}>
                        <Link to={link.href} className="luxury-footer-link">
                          {link.label}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </FooterAccordionItem>
              </div>

              {/* Account */}
              <div className="col-lg-3 col-md-6 luxury-footer-col">
                <FooterAccordionItem
                  id="footer9-account"
                  className="footer-col-block"
                  heading={formatTitle(footerAccountLinksPage.title)}
                  headingClassName="luxury-footer-heading"
                >
                  <ul className="luxury-footer-links">
                    {footerAccountLinksPage.links.map((link) => (
                      <li key={link.href + link.label}>
                        <Link to={link.href} className="luxury-footer-link">
                          {link.label}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </FooterAccordionItem>
              </div>

            </div>
          </FooterAccordionWrapper>

          {/* ── Footer bottom bar ── */}
          <div className="luxury-footer-bottom">
            <p className="luxury-copyright">
              ©{new Date().getFullYear()} 2Deal. All Rights Reserved.
            </p>
            <ul className="luxury-payment-list">
              {footerPaymentIcons.map((icon) => (
                <li key={icon.src}>
                  <img src={icon.src} alt={icon.alt} width={38} height={24} loading="lazy" />
                </li>
              ))}
            </ul>
          </div>

        </div>
      </div>

    </footer>
  );
}
