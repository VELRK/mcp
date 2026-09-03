let cachedSymbol = "₹";

export function setCurrencySymbol(symbol?: string | null): void {
  if (symbol != null && String(symbol).trim() !== "") {
    cachedSymbol = String(symbol).trim();
  }
}

export function getCurrencySymbol(): string {
  return cachedSymbol;
}

export function formatPrice(value: number, symbol?: string): string {
  return (
    (symbol ?? cachedSymbol) +
    Number(value || 0).toLocaleString("en-IN", {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    })
  );
}

/** Format API/DB datetime (naive local or ISO) for display in Asia/Kolkata. */
export function formatDateTime(
  value?: string | null,
  opts: Intl.DateTimeFormatOptions = {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  },
): string {
  if (!value) return "—";
  const raw = String(value).trim();
  // Treat naive "YYYY-MM-DD HH:mm:ss" as Asia/Kolkata wall clock
  const naive = raw.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/);
  let date: Date;
  if (naive && !/[Zz]|[+\-]\d{2}:?\d{2}$/.test(raw)) {
    date = new Date(`${naive[1]}T${naive[2]}+05:30`);
  } else {
    date = new Date(raw.includes("T") ? raw : raw.replace(" ", "T"));
  }
  if (Number.isNaN(date.getTime())) return raw;
  return date.toLocaleString("en-IN", { timeZone: "Asia/Kolkata", ...opts });
}

export function formatDate(value?: string | null): string {
  return formatDateTime(value, {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}
