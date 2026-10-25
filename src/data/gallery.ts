export interface GalleryItem {
  id: string;
  src: string;
  title: string;
  location: string;
  category: string;
  span?: "wide" | "tall" | "normal";
}

const galleryImages = import.meta.glob("../assets/imgs/gallary/*.webp", {
  eager: true,
  import: "default",
}) as Record<string, string>;

const titleOverrides: Record<string, string> = {
  "Forodhani-Night-Market-Stone-Town-Zanzibar": "Forodhani Night Market",
  "zanzibar-old-slave-market-anglican-cathedral-tanzania-1536x1075": "Old Slave Market and Anglican Cathedral",
  "zanzibar-island-kizimkazi-beach-the-residence-hotel-orig": "Kizimkazi Beach",
  "prison_island_tours-in-zanzibar": "Prison Island",
  "pexels-droneafrica-17732689": "Zanzibar Coastline",
  "rowen-smith-NMcvybDrCYY-unsplash-1536x1024": "Zanzibar Landscape",
  "beautiful-zebra-wild": "Zebra Safari",
  "beautiful-zebra-wild (1)": "Zebra in the Wild",
  "breathtaking-view-trees-foggy-hills-captured-sunset-hawke-s-bay-new-zealand": "Misty Highland Sunset",
  "chair-table-dinning-beach-sea-with-blue-sky": "Beach Dining",
  "elephant-herd-against-sunset": "Elephant Herd at Sunset",
  "photorealistic-scene-wild-elephants": "Wild Elephants",
  "view-wild-rhino-nature": "Wild Rhino",
};

const getFileName = (path: string) => path.split("/").pop()?.replace(/\.(jpg|jpeg|png|webp)$/i, "") ?? path;

const toTitle = (name: string) =>
  titleOverrides[name] ??
  name
    .replace(/\s*\(\d+\)/g, "")
    .replace(/[-_]+/g, " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

const getCategory = (name: string) => {
  const lower = name.toLowerCase();
  if (lower.includes("elephant") || lower.includes("zebra") || lower.includes("giraffe") || lower.includes("rhino") || lower.includes("safari")) return "Safaris";
  if (lower.includes("jozani") || lower.includes("chumbe") || lower.includes("kilimanjaro") || lower.includes("forest") || lower.includes("foggy")) return "Nature";
  if (lower.includes("forodhani") || lower.includes("slave") || lower.includes("stone") || lower.includes("kizimbani")) return "Culture";
  if (lower.includes("mnemba") || lower.includes("nungwi") || lower.includes("nugwi") || lower.includes("michamvi") || lower.includes("kizimkazi") || lower.includes("beach") || lower.includes("zanzibar")) return "Beaches";
  return "Activities";
};

const getLocation = (name: string) => {
  const lower = name.toLowerCase();
  if (lower.includes("serengeti") || lower.includes("kilimanjaro") || lower.includes("elephant") || lower.includes("zebra") || lower.includes("giraffe") || lower.includes("rhino")) return "Tanzania";
  if (lower.includes("jozani")) return "Jozani Forest, Zanzibar";
  if (lower.includes("forodhani") || lower.includes("stone") || lower.includes("slave")) return "Stone Town, Zanzibar";
  if (lower.includes("mnemba")) return "Mnemba Island, Zanzibar";
  if (lower.includes("nungwi") || lower.includes("nugwi")) return "Nungwi, Zanzibar";
  if (lower.includes("michamvi")) return "Michamvi, Zanzibar";
  if (lower.includes("kizimkazi")) return "Kizimkazi, Zanzibar";
  return "Zanzibar";
};

export const galleryItems: GalleryItem[] = Object.entries(galleryImages).map(([path, src], index) => {
  const fileName = getFileName(path);

  return {
    id: String(index + 1),
    src,
    title: toTitle(fileName),
    location: getLocation(fileName),
    category: getCategory(fileName),
    span: index % 7 === 0 ? "wide" : index % 5 === 0 ? "tall" : "normal",
  };
});

export const galleryCategories = ["All", "Beaches", "Safaris", "Nature", "Culture", "Activities"];



