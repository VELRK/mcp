import { useCallback, useEffect, useMemo, useState, type FormEvent } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { shippingAPI, type ShippingTrackResult } from "@/services/api";

type TrackEvent = {
  time?: string;
  desc?: string;
  label?: string;
};

/** Split "EN / MS" (or 【EN / MS】) into bilingual lines for display. */
function splitBilingual(text: string): { en: string; ms?: string } {
  const cleaned = text
    .replace(/^[\s\u2014\-–]+/, "")
    .replace(/[【\[]/g, "")
    .replace(/[】\]]/g, "")
    .trim();

  const reasonSplit = cleaned.match(/^(.*?reason\s+is\s+)(.+?)\s*\/\s*(.+)$/i);
  if (reasonSplit) {
    return {
      en: `${reasonSplit[1]}${reasonSplit[2]}`.replace(/,\s*/g, ", ").trim(),
      ms: reasonSplit[3].trim(),
    };
  }

  const slash = cleaned.match(/^(.+?)\s*\/\s*(.+)$/);
  if (slash && /[A-Za-z]/.test(slash[1]) && /[A-Za-z]/.test(slash[2])) {
    return { en: slash[1].trim(), ms: slash[2].trim() };
  }

  return { en: cleaned.replace(/,\s*/g, ", ") };
}

function statusTone(status?: string | null, latestEn?: string): "ok" | "hold" | "ship" | "warn" | "neutral" {
  const s = (status || "").toLowerCase();
  const t = (latestEn || "").toLowerCase();
  if (s === "delivered" || t.includes("delivered")) return "ok";
  if (s === "cancelled" || s === "returned" || t.includes("return")) return "warn";
  if (t.includes("on hold") || t.includes("exception") || t.includes("failed")) return "hold";
  if (s === "shipped" || s === "processing" || s === "confirmed") return "ship";
  return "neutral";
}

function OrderTracking() {
  const [params] = useSearchParams();
  const initial = params.get("tracking") || params.get("awb") || params.get("order") || "";
  const [query, setQuery] = useState(initial);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<ShippingTrackResult | null>(null);

  const track = useCallback(
    async (e?: FormEvent, override?: string) => {
      e?.preventDefault();
      const q = (override ?? query).trim();
      if (!q) {
        setError("Enter your tracking ID (AWB) or order number.");
        return;
      }
      const looksLikeOrderNo = /^SK/i.test(q) || q.includes("-");
      setLoading(true);
      setError(null);
      setResult(null);
      try {
        const payload = looksLikeOrderNo
          ? { order_number: q, tracking_number: q }
          : { tracking_number: q };
        const res = await shippingAPI.track(payload);
        const data = res.data?.data;
        if (!res.data?.success || !data) {
          setError(res.data?.message || "Shipment not found.");
          return;
        }
        setResult(data);
      } catch (err: unknown) {
        const msg =
          (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
          "Could not fetch tracking. Check the ID and try again.";
        setError(msg);
      } finally {
        setLoading(false);
      }
    },
    [query],
  );

  useEffect(() => {
    if (initial) {
      void track(undefined, initial);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initial]);

  const events: TrackEvent[] = useMemo(() => {
    if (result?.events?.length) return result.events;
    return (result?.tracks ?? []).map((t) => {
      const ev = t as {
        scanTime?: string;
        time?: string;
        desc?: string;
        remark?: string;
        scanType?: string;
      };
      return {
        time: ev.scanTime || ev.time || "",
        desc: ev.desc || ev.remark || ev.scanType || "",
        label: [ev.scanTime || ev.time, ev.desc || ev.remark || ev.scanType]
          .filter(Boolean)
          .join(" — "),
      };
    });
  }, [result]);

  const latest = events[0];
  const latestText = (latest?.desc || latest?.label || result?.courier_status || "").replace(
    /^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s*[—\-–]\s*/,
    "",
  );
  const latestBi = latestText ? splitBilingual(latestText) : null;
  const latestTime = latest?.time || "";
  const tone = statusTone(result?.order_status, latestBi?.en);

  return (
    <div className="flat-spacing pt-0">
      <style>{`
        .ot-wrap { max-width: 720px; margin: 0 auto; }
        .ot-search {
          background: linear-gradient(180deg, #f3fbfa 0%, #ffffff 100%);
          border: 1px solid #d9efed;
          border-radius: 16px;
          padding: 18px;
          box-shadow: 0 10px 30px rgba(15, 118, 110, 0.05);
        }
        .ot-form {
          display: flex;
          gap: 10px;
          align-items: stretch;
        }
        .ot-form input {
          flex: 1;
          min-width: 0;
          height: 52px;
          border: 1px solid #d7e3e2;
          border-radius: 12px;
          padding: 0 16px;
          font-size: 15px;
          background: #fff;
          outline: none;
          transition: border-color .15s, box-shadow .15s;
        }
        .ot-form input:focus {
          border-color: #3ec1bc;
          box-shadow: 0 0 0 4px rgba(62, 193, 188, 0.16);
        }
        .ot-form button {
          flex: 0 0 auto;
          min-width: 120px;
          height: 52px;
          border: 0;
          border-radius: 12px;
          background: #0f766e;
          color: #fff;
          font-weight: 650;
          font-size: 15px;
          padding: 0 22px;
          transition: background .15s, transform .15s;
        }
        .ot-form button:hover:not(:disabled) { background: #0d9488; }
        .ot-form button:active:not(:disabled) { transform: translateY(1px); }
        .ot-form button:disabled { opacity: .65; cursor: wait; }
        @media (max-width: 560px) {
          .ot-form { flex-direction: column; }
          .ot-form button { width: 100%; min-width: 0; }
        }
        .ot-error {
          margin-top: 14px;
          padding: 12px 14px;
          border-radius: 12px;
          background: #fef2f2;
          border: 1px solid #fecaca;
          color: #991b1b;
          font-size: 14px;
        }
        .ot-card {
          margin-top: 22px;
          background: #fff;
          border: 1px solid #e6eceb;
          border-radius: 18px;
          overflow: hidden;
          box-shadow: 0 12px 36px rgba(17, 24, 39, 0.05);
        }
        .ot-card-head {
          padding: 20px 22px 16px;
          display: grid;
          grid-template-columns: 1fr auto;
          gap: 16px;
          align-items: start;
          background:
            linear-gradient(90deg, rgba(62,193,188,.12), transparent 55%),
            #fafcfb;
          border-bottom: 1px solid #eef3f2;
        }
        .ot-meta-label {
          font-size: 11px;
          letter-spacing: .06em;
          text-transform: uppercase;
          color: #7b8790;
          margin-bottom: 6px;
          font-weight: 650;
        }
        .ot-meta-value {
          font-size: 16px;
          font-weight: 700;
          color: #111827;
          word-break: break-all;
          line-height: 1.3;
        }
        .ot-meta-side { text-align: right; }
        .ot-badges {
          display: flex;
          flex-wrap: wrap;
          gap: 8px;
          padding: 16px 22px 0;
        }
        .ot-badge {
          display: inline-flex;
          align-items: center;
          gap: 6px;
          padding: 6px 12px;
          border-radius: 999px;
          font-size: 12px;
          font-weight: 650;
          text-transform: capitalize;
          background: #f3f4f6;
          color: #374151;
        }
        .ot-badge::before {
          content: "";
          width: 6px;
          height: 6px;
          border-radius: 50%;
          background: currentColor;
          opacity: .7;
        }
        .ot-badge--ship { background: #111827; color: #fff; }
        .ot-badge--ok { background: #059669; color: #fff; }
        .ot-badge--warn { background: #b45309; color: #fff; }
        .ot-badge--carrier {
          background: #e6f7f6;
          color: #0f766e;
        }
        .ot-badge--carrier::before { background: #3ec1bc; opacity: 1; }
        .ot-empty {
          padding: 18px 22px 8px;
          color: #6b7280;
          font-size: 14px;
          line-height: 1.5;
          margin: 0;
        }
        .ot-latest {
          margin: 16px 22px 0;
          padding: 16px 16px 16px 18px;
          border-radius: 14px;
          position: relative;
          overflow: hidden;
        }
        .ot-latest::before {
          content: "";
          position: absolute;
          left: 0; top: 0; bottom: 0;
          width: 4px;
        }
        .ot-latest--ship { background: #f0fdfa; border: 1px solid #99f6e4; }
        .ot-latest--ship::before { background: #14b8a6; }
        .ot-latest--ok { background: #ecfdf5; border: 1px solid #a7f3d0; }
        .ot-latest--ok::before { background: #10b981; }
        .ot-latest--hold { background: #fffbeb; border: 1px solid #fde68a; }
        .ot-latest--hold::before { background: #f59e0b; }
        .ot-latest--warn { background: #fff7ed; border: 1px solid #fed7aa; }
        .ot-latest--warn::before { background: #ea580c; }
        .ot-latest--neutral { background: #f8fafc; border: 1px solid #e2e8f0; }
        .ot-latest--neutral::before { background: #94a3b8; }
        .ot-latest-kicker {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 12px;
          margin-bottom: 8px;
        }
        .ot-latest-label {
          font-size: 11px;
          letter-spacing: .06em;
          text-transform: uppercase;
          font-weight: 700;
          color: #64748b;
        }
        .ot-latest-time {
          font-size: 12px;
          color: #64748b;
          font-weight: 600;
          font-variant-numeric: tabular-nums;
        }
        .ot-latest-en {
          font-size: 15px;
          line-height: 1.5;
          color: #0f172a;
          font-weight: 650;
        }
        .ot-latest-ms {
          font-size: 13px;
          line-height: 1.45;
          color: #64748b;
          margin-top: 8px;
          padding-top: 8px;
          border-top: 1px dashed rgba(100, 116, 139, 0.25);
        }
        .ot-timeline { padding: 20px 22px 10px; }
        .ot-timeline-title {
          font-size: 11px;
          letter-spacing: .06em;
          text-transform: uppercase;
          color: #7b8790;
          margin-bottom: 16px;
          font-weight: 700;
        }
        .ot-event {
          position: relative;
          padding: 0 0 18px 20px;
          border-left: 2px solid #d8f0ee;
        }
        .ot-event:last-child { border-left-color: transparent; padding-bottom: 2px; }
        .ot-event::before {
          content: "";
          position: absolute;
          left: -5px;
          top: 5px;
          width: 8px;
          height: 8px;
          border-radius: 50%;
          background: #3ec1bc;
          box-shadow: 0 0 0 3px #e6f7f6;
        }
        .ot-event.is-first::before {
          background: #0f766e;
          box-shadow: 0 0 0 3px #ccfbf1;
        }
        .ot-event-en { font-size: 14px; line-height: 1.45; color: #111827; font-weight: 600; }
        .ot-event-ms { font-size: 12.5px; line-height: 1.4; color: #6b7280; margin-top: 4px; }
        .ot-event-time {
          font-size: 12px;
          color: #94a3b8;
          margin-top: 6px;
          font-variant-numeric: tabular-nums;
        }
        .ot-foot {
          margin-top: 8px;
          padding: 14px 22px 16px;
          border-top: 1px solid #eef3f2;
          font-size: 13px;
          color: #6b7280;
          background: #fafcfb;
        }
        .ot-foot a { color: #0f766e; font-weight: 700; text-decoration: none; }
        .ot-foot a:hover { text-decoration: underline; }
      `}</style>

      <div className="container">
        <div className="ot-wrap">
          <div className="ot-search">
            <form className="ot-form form-tracking" onSubmit={track}>
              <input
                type="text"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Tracking ID (AWB) or order number"
                required
                autoComplete="off"
                aria-label="Tracking ID or order number"
              />
              <button type="submit" disabled={loading}>
                {loading ? "Tracking…" : "Track"}
              </button>
            </form>
          </div>

          {error && (
            <div className="ot-error" role="alert">
              {error}
            </div>
          )}

          {result && (
            <div className="ot-card">
              <div className="ot-card-head">
                <div>
                  <div className="ot-meta-label">Tracking ID</div>
                  <div className="ot-meta-value">{result.tracking_number || "—"}</div>
                </div>
                {result.order_number && (
                  <div className="ot-meta-side">
                    <div className="ot-meta-label">Order</div>
                    <div className="ot-meta-value">{result.order_number}</div>
                  </div>
                )}
              </div>

              <div className="ot-badges">
                {result.order_status && (
                  <span
                    className={`ot-badge ${
                      tone === "ok"
                        ? "ot-badge--ok"
                        : tone === "warn"
                          ? "ot-badge--warn"
                          : tone === "ship" || tone === "hold"
                            ? "ot-badge--ship"
                            : ""
                    }`}
                  >
                    {result.order_status}
                  </span>
                )}
              </div>

              {!result.has_tracking && !result.tracking_number ? (
                <p className="ot-empty">
                  {result.message || "No tracking ID yet. It will appear once the shipment is created."}
                </p>
              ) : events.length === 0 ? (
                <p className="ot-empty">
                  Tracking ID found. No scan events yet — check again after pickup.
                </p>
              ) : (
                <>
                  {latestBi && (
                    <div className={`ot-latest ot-latest--${tone}`}>
                      <div className="ot-latest-kicker">
                        <span className="ot-latest-label">Current status</span>
                        {latestTime && <span className="ot-latest-time">{latestTime}</span>}
                      </div>
                      <div className="ot-latest-en">{latestBi.en}</div>
                      {latestBi.ms && <div className="ot-latest-ms">{latestBi.ms}</div>}
                    </div>
                  )}

                  {events.length > 1 && (
                    <div className="ot-timeline">
                      <div className="ot-timeline-title">Shipment history</div>
                      {events.slice(1).map((ev, i) => {
                        const raw = (ev.desc || ev.label || "Update").replace(
                          /^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s*[—\-–]\s*/,
                          "",
                        );
                        const bi = splitBilingual(raw);
                        return (
                          <div key={i} className={`ot-event${i === 0 ? " is-first" : ""}`}>
                            <div className="ot-event-en">{bi.en}</div>
                            {bi.ms && <div className="ot-event-ms">{bi.ms}</div>}
                            {ev.time && <div className="ot-event-time">{ev.time}</div>}
                          </div>
                        );
                      })}
                    </div>
                  )}
                </>
              )}

              <div className="ot-foot">
                Logged-in customers can also open{" "}
                <Link to="/account-orders">My Orders</Link> to track shipments.
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default OrderTracking;
