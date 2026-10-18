// Contraste WCAG 2, igual ao replica/design (contrast.py). Usado pelo widget,
// cuja cor de destaque vem do cliente: o texto em cima dela tem de ficar legível.
function luminance(hex: string) {
  const n = parseInt(hex.slice(1), 16);
  const chan = (c: number) => {
    const s = c / 255;
    return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * chan((n >> 16) & 255) + 0.7152 * chan((n >> 8) & 255) + 0.0722 * chan(n & 255);
}

export function contrastRatio(a: string, b: string) {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

/** Branco ou quase-preto, o que der mais contraste sobre `bg`. */
export function onColor(bg: string) {
  return contrastRatio("#ffffff", bg) >= contrastRatio("#111418", bg) ? "#ffffff" : "#111418";
}
