const services = [
  {
    icon: (
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
        <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2l.5-.5m10.5-10.5-2.5 2.5m2.5-2.5 2.5-2.5M10.5 4.5 8 7m4 14a9 9 0 0 0 9-9 9 9 0 0 0-9-9 9 9 0 0 0-9 9c0 2.12.74 4.07 1.97 5.61" />
        <path d="M22 2 11 13" />
      </svg>
    ),
    title: "Free Shipping",
    description: "Free shipping on qualifying orders."
  },
  {
    icon: (
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
        <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" />
        <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" />
        <path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />
      </svg>
    ),
    title: "Easy Payment",
    description: "Quick, easy and secure payment modes to choose from."
  },
  {
    icon: (
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
        <path d="M3 18v-6a9 9 0 0 1 18 0v6" />
        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z" />
        <path d="M12 22v-3" />
      </svg>
    ),
    title: "Support",
    description: "Reach us at +60126364666\nBetween Monday to Saturday"
  },
];

export default function ServicesBanner() {
  return (
    <div style={{ backgroundColor: '#f2f4f5', padding: '40px 0', borderTop: '1px solid #eaeaea', borderBottom: '1px solid #eaeaea' }}>
      <div className="container">
        <div style={{
          display: 'flex',
          flexWrap: 'wrap',
          justifyContent: 'space-between',
          alignItems: 'center',
          gap: '30px'
        }}>
          {services.map((svc, idx) => (
            <div
              key={idx}
              style={{
                display: 'flex',
                alignItems: 'center',
                flex: '1 1 300px',
                gap: '20px',
              }}
            >
              <div style={{
                minWidth: '70px',
                width: '70px',
                height: '70px',
                borderRadius: '50%',
                backgroundColor: '#3ec1bc',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: '#fff',
                flexShrink: 0,
                boxShadow: '0 4px 15px rgba(62, 193, 188, 0.3)'
              }}>
                {svc.icon}
              </div>
              <div style={{
                display: 'flex',
                flexDirection: 'column',
                gap: '5px'
              }}>
                <h6 style={{
                  margin: 0,
                  fontSize: '16px',
                  fontWeight: '600',
                  color: '#222',
                  fontFamily: '"Inter", sans-serif'
                }}>{svc.title}</h6>
                <p style={{
                  margin: 0,
                  fontSize: '13px',
                  lineHeight: '1.5',
                  color: '#555',
                  whiteSpace: 'pre-line'
                }}>{svc.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
