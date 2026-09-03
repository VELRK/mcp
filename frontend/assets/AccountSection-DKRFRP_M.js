import{B as p,p as d,o as u,j as o,L as t}from"./index-jupH7x8z.js";function m(c){return null}const l=[{href:"/account-page",label:"Dashboard",icon:"icon-HouseLine"},{href:"/account-orders",label:"My Orders",icon:"icon-Package"},{href:"/account-addresses",label:"My Addresses",icon:"icon-storefront"},{href:"/account-setting",label:"Settings",icon:"icon-GearSix"}];function x(){const{pathname:c}=p(),s=d(),{logout:r}=u();function n(){r(),s("/")}const a=e=>e==="icon-Wallet"?o.jsx("span",{className:"icon",style:{display:"flex"},children:o.jsxs("svg",{width:"18",height:"18",viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:"1.8",strokeLinecap:"round",strokeLinejoin:"round",children:[o.jsx("path",{d:"M21 12V7H5a2 2 0 0 1 0-4h14v4"}),o.jsx("path",{d:"M3 5v14a2 2 0 0 0 2 2h16v-5"}),o.jsx("path",{d:"M18 12a2 2 0 0 0 0 4h4v-4Z"})]})}):e==="icon-star"?o.jsx("span",{className:"icon",style:{display:"flex"},children:o.jsx("svg",{width:"18",height:"18",viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:"1.8",strokeLinecap:"round",strokeLinejoin:"round",children:o.jsx("polygon",{points:"12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"})})}):e==="icon-CreditCard"?o.jsx("span",{className:"icon",style:{display:"flex"},children:o.jsxs("svg",{width:"18",height:"18",viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:"1.8",strokeLinecap:"round",strokeLinejoin:"round",children:[o.jsx("rect",{x:"2",y:"5",width:"20",height:"14",rx:"2"}),o.jsx("line",{x1:"2",y1:"10",x2:"22",y2:"10"})]})}):o.jsx("i",{className:`icon ${e}`});return o.jsxs("div",{className:"account-sidebar-wrapper",children:[o.jsx("style",{children:`
        /* Desktop Sidebar View */
        .account-sidebar-desktop {
          display: block;
          background: #ffffff;
          border-radius: 16px;
          border: 1px solid rgba(0, 0, 0, 0.04);
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
          padding: 16px 12px;
          margin-bottom: 30px;
          position: sticky;
          top: 100px;
        }

        .my-account-nav-custom {
          display: flex;
          flex-direction: column;
          gap: 6px;
        }

        .link-account-custom {
          display: flex;
          align-items: center;
          gap: 14px;
          padding: 12px 18px;
          color: #666666;
          font-family: 'Inter', sans-serif;
          font-size: 15px;
          font-weight: 500;
          border-radius: 8px;
          background: transparent;
          transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
          text-decoration: none !important;
          width: 100%;
          text-align: left;
          border: none !important;
        }

        .link-account-custom .icon {
          font-size: 20px;
          color: #888888;
          transition: all 0.25s ease;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .link-account-custom:hover {
          color: #3ec1bc;
          background: #faf5f6;
        }

        .link-account-custom:hover .icon {
          color: #3ec1bc;
          transform: scale(1.05);
        }

        .link-account-custom.active {
          color: #3ec1bc;
          background: #faf0f2;
          font-weight: 600;
        }

        .link-account-custom.active .icon {
          color: #3ec1bc;
        }

        .logout-btn-custom {
          border-top: 1px dashed rgba(62, 193, 188, 0.15) !important;
          border-radius: 0 !important;
          margin-top: 12px;
          padding-top: 18px;
        }

        /* Mobile Responsive Navigation (Hidden on Desktop) */
        .account-sidebar-mobile {
          display: none;
          margin-bottom: 24px;
        }



        .account-mobile-tabs-row {
          display: flex;
          align-items: center;
          gap: 8px;
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
          scrollbar-width: none; /* Firefox */
          padding: 4px 2px 8px 2px;
        }

        .account-mobile-tabs-row::-webkit-scrollbar {
          display: none; /* Chrome / Safari */
        }

        .account-mobile-tab-pill {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          padding: 8px 16px;
          font-size: 13.5px;
          font-weight: 500;
          color: #555555;
          background: #ffffff;
          border: 1px solid #e0e0e0;
          border-radius: 50px;
          white-space: nowrap;
          text-decoration: none !important;
          transition: all 0.2s ease;
          flex-shrink: 0;
        }

        .account-mobile-tab-pill:hover {
          border-color: #3ec1bc;
          color: #3ec1bc;
        }

        .account-mobile-tab-pill.active {
          background: #3ec1bc;
          color: #ffffff;
          border-color: #3ec1bc;
          font-weight: 600;
          box-shadow: 0 4px 12px rgba(62, 193, 188, 0.3);
        }

        .account-mobile-tab-pill.active .icon {
          color: #ffffff !important;
        }

        .account-mobile-tab-pill.logout-pill {
          border-color: #fca5a5;
          color: #dc2626;
          background: #fef2f2;
        }

        @media (max-width: 991.98px) {
          .account-sidebar-desktop {
            display: none;
          }
          .account-sidebar-mobile {
            display: block;
          }
        }
      `}),o.jsx("div",{className:"account-sidebar-desktop",children:o.jsxs("div",{className:"my-account-nav-custom",children:[l.map(e=>{const i=c===e.href;return o.jsxs(t,{to:e.href,className:`link-account-custom ${i?"active":""}`,children:[a(e.icon),o.jsx("span",{children:e.label})]},e.href)}),o.jsxs("button",{type:"button",onClick:n,className:"link-account-custom logout-btn-custom",children:[o.jsx("i",{className:"icon icon-SignOut"}),o.jsx("span",{children:"Logout"})]})]})}),o.jsx("div",{className:"account-sidebar-mobile",children:o.jsxs("div",{className:"account-mobile-tabs-row",children:[l.map(e=>{const i=c===e.href;return o.jsxs(t,{to:e.href,className:`account-mobile-tab-pill ${i?"active":""}`,children:[a(e.icon),o.jsx("span",{children:e.label})]},e.href)}),o.jsxs("button",{type:"button",onClick:n,className:"account-mobile-tab-pill logout-pill",children:[o.jsx("i",{className:"icon icon-SignOut"}),o.jsx("span",{children:"Logout"})]})]})})]})}function f({title:c,sectionClassName:s="flat-spacing",children:r,customBreadcrumbs:n,hideSidebar:a=!1}){return o.jsxs("section",{className:`account-section-custom ${s}`,children:[o.jsx("style",{children:`
        .account-section-custom {
          padding-top: 30px;
          padding-bottom: 60px;
        }

        .classic-breadcrumb-wrapper {
          font-family: 'Inter', sans-serif;
          font-size: 13px;
          font-weight: 500;
          margin-bottom: 24px;
          color: #888;
          display: flex;
          align-items: center;
          gap: 8px;
          flex-wrap: wrap;
        }
        .classic-breadcrumb-wrapper a, .classic-breadcrumb-wrapper .breadcrumb-link {
          color: #555;
          text-decoration: none !important;
          transition: color 0.2s ease;
        }
        .classic-breadcrumb-wrapper a:hover, .classic-breadcrumb-wrapper .breadcrumb-link:hover {
          color: #3ec1bc;
        }
        .classic-breadcrumb-wrapper .separator {
          color: #ccc;
          font-size: 11px;
          margin: 0 2px;
          display: inline-block;
        }
        .classic-breadcrumb-wrapper .current {
          color: #111;
          font-weight: 600;
        }

        @media (max-width: 767.98px) {
          .account-section-custom {
            padding-top: 16px !important;
            padding-bottom: 36px !important;
          }
          .classic-breadcrumb-wrapper {
            font-size: 12px;
            margin-bottom: 16px;
            gap: 6px;
          }
        }
      `}),o.jsx("div",{className:"container",children:o.jsxs("div",{className:"row",children:[!a&&o.jsx("div",{className:"col-lg-3",children:o.jsx(x,{})}),o.jsxs("div",{className:a?"col-lg-12":"col-lg-9",children:[n?o.jsx("div",{className:"classic-breadcrumb-wrapper",children:n}):o.jsxs("div",{className:"classic-breadcrumb-wrapper",children:[o.jsx(t,{to:"/",children:"Home"}),o.jsx("span",{className:"separator",children:">"}),o.jsx(t,{to:"/account-page",children:"My Account"}),c&&o.jsxs(o.Fragment,{children:[o.jsx("span",{className:"separator",children:">"}),o.jsx("span",{className:"current",children:c})]})]}),o.jsx("div",{className:a?"":"my-account-content",children:r})]})]})})]})}export{f as A,m as a};
