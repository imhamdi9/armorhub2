/** "skin-care" → "Skin care": categories are stored as one tidy slug and shown as a readable label. */
export const categoryLabel = (slug: string) => {
  const text = slug.replace(/-/g, " ").trim();
  return text.charAt(0).toUpperCase() + text.slice(1);
};
