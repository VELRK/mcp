import { Link } from "react-router-dom";

function PageTitle() {
  return (
    <>
      <section className="section-page-title text-center flat-spacing-2">
        <div className="container">
          <div className="main-page-title">
            <div className="breadcrumbs">
              <Link to={`/`} className="text-caption-01 cl-text-3 link">
                Home
              </Link>
              <i className="icon icon-CaretRightThin cl-text-3" />
              <p className="text-caption-01">Order Tracking</p>
            </div>
            <h3>Order Tracking</h3>
            <p className="text-body-1 cl-text-2">
              Enter your tracking ID (AWB) or order number and press Track.
              <br className="d-none d-lg-block" />
              Tracking appears after a tracking number is added to your order.
            </p>
          </div>
        </div>
      </section>
    </>
  );
}

export default PageTitle;
