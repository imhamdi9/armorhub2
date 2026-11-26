import { knownCategory } from "@/lib/category-names";
import { currentLocale } from "@/lib/i18n";

export { categoryNamesReady, loadCategoryNames } from "@/lib/category-names";

/** "skin-care" -> the category's own name in the reader's language (or a tidy English label when the name is not known). */
export const categoryLabel = (slug: string) => {
  const known = knownCategory(slug);
  if (known) return currentLocale() === "ar" && known.ar ? known.ar : known.en;
  const text = slug.replace(/-/g, " ").trim();
  return text.charAt(0).toUpperCase() + text.slice(1);
};
