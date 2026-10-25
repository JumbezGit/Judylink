export interface Tour {
  id: string;
  slug: string;
  name: string;
  category: string;
  location: string;
  duration: string;
  groupSize: string;
  rating: number;
  reviewCount: number;
  price: number;
  currency: string;
  description: string;
  highlights: string[];
  includes: string[];
  excludes: string[];
  whatToBring: string[];
  difficulty: string;
  languages: string[];
  pickup: boolean;
  cancellation: string;
  image: string;
  gallery: string[];
  itinerary: { time: string; activity: string }[];
  faqs: { q: string; a: string }[];
  tags: string[];
  isPopular?: boolean;
}

import featuredMnembaIsland from "../assets/imgs/featured_destinations/mnemba_island.webp";
import featuredNgorongoro from "../assets/imgs/featured_destinations/ngorongoro.webp";
import featuredNungwi from "../assets/imgs/featured_destinations/nungwi.webp";
import featuredPaje from "../assets/imgs/featured_destinations/paje.webp";
import featuredSerengeti from "../assets/imgs/featured_destinations/serengeti.webp";
import featuredStoneTown from "../assets/imgs/featured_destinations/stone_town.webp";

const galleryImages = import.meta.glob("../assets/imgs/gallary/*.webp", {
  eager: true,
  import: "default",
}) as Record<string, string>;

const image = (fileName: string) => galleryImages[`../assets/imgs/gallary/${fileName}`] ?? Object.values(galleryImages)[0];

const tourImages = {
  prisonIsland: ["prison_island_tours-in-zanzibar.webp", "zanzibar.webp", "Mnemba-Island.webp", "Nungwi.webp", "Forodhani-Night-Market-Stone-Town-Zanzibar.webp"].map(image),
  nakupenda: ["zanzibar.webp", "chair-table-dinning-beach-sea-with-blue-sky.webp", "Mnemba2.webp", "Nungwi12.webp", "Michamvi.webp"].map(image),
  stoneTown: ["Forodhani-Night-Market-Stone-Town-Zanzibar.webp", "zanzibar-old-slave-market-anglican-cathedral-tanzania-1536x1075.webp", "zanzibar.webp", "kizimbani.webp", "rowen-smith-NMcvybDrCYY-unsplash-1536x1024.webp"].map(image),
  spiceFarm: ["kizimbani.webp", "zanzibar-old-slave-market-anglican-cathedral-tanzania-1536x1075.webp", "Forodhani-Night-Market-Stone-Town-Zanzibar.webp", "zanzibar.webp", "rowen-smith-NMcvybDrCYY-unsplash-1536x1024.webp"].map(image),
  mnemba: ["Mnemba-Island.webp", "Mnemba.webp", "Mnemba2.webp", "Mnemba3.webp", "Mnemba4.webp"].map(image),
  village: ["rowen-smith-NMcvybDrCYY-unsplash-1536x1024.webp", "zanzibar.webp", "kizimbani.webp", "Nungwi1.webp", "Forodhani-Night-Market-Stone-Town-Zanzibar.webp"].map(image),
  jozani: ["Jozani.webp", "Jozani2.webp", "Jozani3.webp", "chumbe.webp", "breathtaking-view-trees-foggy-hills-captured-sunset-hawke-s-bay-new-zealand.webp"].map(image),
  safariBlue: ["safariblue.webp", "Mnemba5.webp", "Mnemba6.webp", "Nungwi8.webp", "zanzibar-island-kizimkazi-beach-the-residence-hotel-orig.webp"].map(image),
  safariTanzania: ["elephant-herd-against-sunset.webp", "giraffe-wild.webp", "beautiful-zebra-wild.webp", "view-wild-rhino-nature.webp", "Kilimanjaro.webp"].map(image),
  sunsetCruise: ["Nungwi15.webp", "Nungwi14.webp", "Nungwi13.webp", "Michamvi.webp", "zanzibar-island-kizimkazi-beach-the-residence-hotel-orig.webp"].map(image),
  aquarium: ["Nungwi10.webp", "Nungwi11.webp", "Nungwi12.webp", "Nungwi.webp", "nugwi_old.webp"].map(image),
  transport: ["pexels-droneafrica-17732689.webp", "zanzibar.webp", "Forodhani-Night-Market-Stone-Town-Zanzibar.webp", "Nungwi1.webp", "Michamvi.webp"].map(image),
};

const tourGalleries = Object.values(tourImages);

const makeTour = (
  id: string,
  name: string,
  category: string,
  location: string,
  duration: string,
  price: number,
  description: string,
  highlights: string[],
  imageIndex: number,
): Tour => ({
  id,
  slug: name.toLowerCase().replace(/&/g, "and").replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, ""),
  name,
  category,
  location,
  duration,
  groupSize: category === "Transport" ? "Private" : "Private or group",
  rating: 4.9,
  reviewCount: 24 + Number(id) * 9,
  price,
  currency: "USD",
  description,
  highlights,
  includes: ["Hotel pickup & drop-off", "Professional guide", "Transportation", "Drinking water"],
  excludes: ["Personal expenses", "Gratuities", "Extra activities not listed"],
  whatToBring: ["Comfortable clothing", "Sunscreen", "Camera", "Cash for personal expenses"],
  difficulty: "Easy",
  languages: ["English", "Italian", "French", "Spanish", "German", "Swahili"],
  pickup: true,
  cancellation: "Free cancellation up to 24 hours before",
  image: tourGalleries[imageIndex % tourGalleries.length][0],
  gallery: tourGalleries[imageIndex % tourGalleries.length],
  itinerary: [
    { time: "Start", activity: "Pickup from your hotel and introduction from your guide" },
    { time: "Tour", activity: highlights[0] },
    { time: "Experience", activity: highlights[1] || "Enjoy the main tour experience" },
    { time: "Return", activity: "Drop-off at your hotel after the tour" },
  ],
  faqs: [
    { q: "Is hotel pickup included?", a: "Yes, transportation from your hotel is included unless a custom meeting point is arranged." },
    { q: "Can this tour be private?", a: "Yes, we can arrange this experience as a private tour or a group tour." },
  ],
  tags: [category.toLowerCase(), location.toLowerCase()],
});

const baseTours: Tour[] = [
  makeTour(
    "1",
    "Prison Island",
    "Mnemba",
    "Prison Island, Zanzibar",
    "Half Day",
    45,
    "Prison Island is home to giant tortoises and has a fascinating history. This half-day tour includes an excellent knowledgeable guide, optional swimming around the island, and hotel transportation from anywhere you are staying. The boat ride from Stone Town to Prison Island takes about 30 minutes each way. Distance is not an issue - Hakuna Matata!",
    ["Meet the giant tortoises of Prison Island", "Learn the island's fascinating history", "Enjoy an optional swim around the island", "Travel by boat from Stone Town"],
    0,
  ),
  makeTour(
    "2",
    "Nakupenda Sandbank Picnic",
    "Mnemba",
    "Nakupenda Sandbank, Zanzibar",
    "Half Day",
    55,
    "This is a great place to relax, chill, and swim. The tour includes a seafood lunch with fresh fruit on a beautiful sandbank in the middle of the ocean, making it perfect for picnics, wedding celebrations, or engagement proposals. We organize everything for you.",
    ["Relax and swim on Nakupenda Sandbank", "Enjoy seafood lunch and fresh fruit", "Plan a picnic, wedding celebration, or proposal", "Spend time on a sandbank in the ocean"],
    1,
  ),
  makeTour(
    "3",
    "Stone Town Tour",
    "Mnemba",
    "Stone Town, Zanzibar",
    "2 Hours",
    30,
    "You cannot visit Zanzibar without exploring Stone Town, a key historical site that tells the story of colonialism, sultans, and the slave trade. You will also see the house where Freddie Mercury was born and learn about his history and why he was born in Zanzibar. Our experienced guide will share the important stories of Stone Town.",
    ["Explore the history of Stone Town", "Learn about sultans, colonialism, and the slave trade", "Visit the Freddie Mercury house area", "Walk with an experienced local guide"],
    2,
  ),
  makeTour(
    "4",
    "Spice Farm Tour",
    "Spice Farm",
    "Zanzibar Spice Farms",
    "2 Hours",
    35,
    "Zanzibar is famous for its naturally fragrant and flavorful spices. On this walking tour, you will learn how spice trees are grown and how their fruits are produced. After the tour, you can visit a spice shop and purchase spices if you wish. Do not miss this opportunity - let's go together and experience the spice farms!",
    ["Walk through a Zanzibar spice farm", "Learn how spices grow naturally", "Taste and smell fresh island spices", "Visit a spice shop after the tour"],
    3,
  ),
  makeTour(
    "5",
    "Mnemba Island Tour",
    "Mnemba Island",
    "Mnemba Island, Zanzibar",
    "Half Day",
    75,
    "Mnemba is a private island off the coast of Zanzibar, reached by a short boat ride. On this tour, we go swimming with dolphins and snorkeling in one of the best spots in Zanzibar. We do not dock on the island because it is private. Seafood lunch and soft drinks are provided.",
    ["Snorkel near Mnemba Island", "Swim with dolphins when conditions allow", "Enjoy seafood lunch and soft drinks", "Travel by boat along Zanzibar's coast"],
    4,
  ),
  makeTour(
    "6",
    "Village Tour",
    "Village Tour",
    "Local Village, Zanzibar",
    "Half Day",
    40,
    "This tour offers a glimpse into the real life of Zanzibar. We visit a local school, meet nursery students, and walk through a village to observe daily life. Despite economic challenges, people live in harmony, depending on their faith and enjoying a peaceful life. Let's go together and experience the life of Zanzibar.",
    ["Visit a local school", "Meet nursery students", "Walk through a Zanzibar village", "Learn about local daily life"],
    5,
  ),
  makeTour(
    "7",
    "Jozani Forest Tour",
    "Jozani Forest",
    "Jozani Chwaka Bay National Park",
    "2 Hours",
    45,
    "Jozani Forest, also known as Jozani Chwaka Bay National Park, is the only national park on Zanzibar Island. The forest is home to the rare red colobus monkey, which can only be found in Zanzibar. During the two-hour walk, you will also see mangrove trees.",
    ["See the rare Zanzibar red colobus monkey", "Walk through Jozani Forest", "Explore the mangrove boardwalk", "Visit Zanzibar's only national park"],
    6,
  ),
  makeTour(
    "8",
    "Safari Blue Tour",
    "Safari Blue",
    "Fumba, Zanzibar",
    "Full Day",
    80,
    "Safari Blue is Zanzibar's most talked-about sea adventure. This full-day excursion departs from the fishing village of Fumba and includes seafood lunch, fresh fruit, swimming, snorkeling, island walking, relaxing, shopping, and local music and dancing. You can choose a private or shared boat. Hakuna Matata!",
    ["Depart by boat from Fumba village", "Swim and snorkel in clear ocean water", "Enjoy seafood lunch and fresh fruit", "Experience local music and dancing"],
    7,
  ),
  makeTour(
    "9",
    "Safari Tanzania Tour",
    "Safari Tanzania",
    "Mainland Tanzania",
    "1 Day or 3 Days",
    350,
    "Go on safari in Tanzania and experience wild animals in their natural habitat. We offer two options: a one-day trip by plane or a two-night, three-day trip by boat. Both options are safe and peaceful. The longer trip includes a cultural visit on the first day and a full-day game drive with lunch in the park on the second day.",
    ["See wild animals in their natural habitat", "Choose a one-day flight safari or three-day trip", "Enjoy a full-day jeep game drive", "Have lunch inside the park"],
    8,
  ),
  makeTour(
    "10",
    "Sunset Cruise Tour",
    "Sunset Cruise",
    "Zanzibar Coast",
    "Evening",
    45,
    "We start this experience around 4:00 PM as the sun begins to set. We take a boat out to enjoy the beautiful sunset over the water. Onboard, we provide soft drinks, snacks, and fresh fruit for a relaxed evening experience.",
    ["Cruise along the coast around sunset", "Enjoy soft drinks, snacks, and fresh fruit", "Watch the sun set over the water", "Relax on a peaceful boat experience"],
    9,
  ),
  makeTour(
    "11",
    "Aquarium",
    "Aquarium",
    "Nungwi, Zanzibar",
    "Flexible Visit",
    25,
    "This natural swimming pool is located in Nungwi in northern Zanzibar. You can swim with turtles in a safe and fascinating saltwater environment connected to the ocean. The clear water makes for stunning photos. The pool is open from 8:00 AM to 6:30 PM.",
    ["Swim with turtles in Nungwi", "Visit a natural saltwater pool", "Take beautiful photos in clear water", "Enjoy a flexible daytime activity"],
    10,
  ),
  makeTour(
    "12",
    "Transport",
    "Transport",
    "Zanzibar",
    "Flexible",
    20,
    "We organize airport-to-hotel transfers, tour transfers, and transportation for other activities. Our professional drivers take you to your destination while sharing brief local information. We also have multilingual tour guides who speak English, Italian, French, Spanish, and German.",
    ["Book airport and hotel transfers", "Arrange transport for tours and activities", "Travel with professional local drivers", "Request multilingual guide support"],
    11,
  ),
];

const tourDetailContent: Record<string, Partial<Tour>> = {
  "1": {
    includes: ["Hotel pickup & drop-off", "Return boat from Stone Town", "Professional guide", "Prison Island entry support", "Drinking water"],
    whatToBring: ["Swimwear", "Towel", "Sunscreen", "Camera", "Comfortable sandals"],
    itinerary: [
      { time: "Pickup", activity: "Pickup from your hotel anywhere in Zanzibar" },
      { time: "Boat Ride", activity: "Travel from Stone Town to Prison Island by boat, about 30 minutes each way" },
      { time: "Island Visit", activity: "Meet the giant tortoises and learn the island's history with your guide" },
      { time: "Swim", activity: "Enjoy optional swimming around the island when sea conditions allow" },
      { time: "Return", activity: "Boat back to Stone Town and transfer to your hotel" },
    ],
    faqs: [
      { q: "How long is the boat ride?", a: "The boat ride from Stone Town to Prison Island takes about 30 minutes each way." },
      { q: "Can I swim on this tour?", a: "Yes, swimming around the island is possible when the weather and sea conditions are suitable." },
    ],
  },
  "2": {
    includes: ["Hotel pickup & drop-off", "Boat transfer", "Seafood lunch", "Fresh fruit", "Soft drinks"],
    whatToBring: ["Swimwear", "Towel", "Sunscreen", "Sunglasses", "Waterproof bag"],
    itinerary: [
      { time: "Pickup", activity: "Pickup from your hotel and transfer to the departure point" },
      { time: "Boat", activity: "Cruise to Nakupenda Sandbank in the middle of the ocean" },
      { time: "Picnic", activity: "Relax, swim, take photos, and enjoy a seafood picnic with fresh fruit" },
      { time: "Return", activity: "Return by boat and transfer back to your hotel" },
    ],
    faqs: [
      { q: "Is lunch included?", a: "Yes, a seafood lunch with fresh fruit is included." },
      { q: "Can you organize a celebration?", a: "Yes, we can arrange picnics, wedding celebrations, and engagement proposals." },
    ],
  },
  "3": {
    includes: ["Experienced walking guide", "Stone Town history tour", "Freddie Mercury house area visit", "Slave trade history briefing", "Drinking water"],
    whatToBring: ["Comfortable walking shoes", "Modest clothing", "Camera", "Sun hat", "Cash for personal shopping"],
    itinerary: [
      { time: "Start", activity: "Meet your guide in Stone Town or arrange pickup" },
      { time: "History Walk", activity: "Explore stories of sultans, colonialism, and the slave trade" },
      { time: "Landmarks", activity: "Visit historic streets, markets, and the Freddie Mercury house area" },
      { time: "Finish", activity: "End with local recommendations from your guide" },
    ],
    faqs: [
      { q: "How long is the Stone Town tour?", a: "The walking tour takes about 2 hours." },
      { q: "Is this tour suitable for children?", a: "Yes, it is an easy walking tour and can be adapted for families." },
    ],
  },
  "4": {
    includes: ["Hotel pickup & drop-off", "Spice farm guide", "Spice tasting", "Fruit tasting", "Spice shop visit"],
    whatToBring: ["Comfortable shoes", "Light clothing", "Sunscreen", "Camera", "Cash if you want to buy spices"],
    itinerary: [
      { time: "Pickup", activity: "Transfer from your hotel to the spice farm" },
      { time: "Walk", activity: "Walk through the farm and learn how spice trees grow" },
      { time: "Tasting", activity: "Smell, taste, and identify Zanzibar spices and tropical fruits" },
      { time: "Shop", activity: "Optional visit to a spice shop before returning to your hotel" },
    ],
    faqs: [
      { q: "Can I buy spices?", a: "Yes, there is an optional spice shop visit after the walking tour." },
      { q: "How long is the tour?", a: "The guided spice farm walk takes about 2 hours." },
    ],
  },
  "5": {
    includes: ["Hotel pickup & drop-off", "Boat transfer", "Snorkeling equipment", "Seafood lunch", "Soft drinks"],
    whatToBring: ["Swimwear", "Towel", "Reef-safe sunscreen", "Waterproof phone pouch", "Underwater camera"],
    itinerary: [
      { time: "Pickup", activity: "Pickup from your hotel and transfer to the beach departure point" },
      { time: "Boat", activity: "Take a short boat ride toward Mnemba Island" },
      { time: "Ocean", activity: "Swim with dolphins when possible and snorkel in clear waters" },
      { time: "Lunch", activity: "Enjoy seafood lunch and soft drinks before returning" },
    ],
    faqs: [
      { q: "Do we dock on Mnemba Island?", a: "No, Mnemba is private, so the tour stays in the surrounding ocean and snorkeling areas." },
      { q: "Are dolphins guaranteed?", a: "Dolphins are wild animals, so sightings depend on natural conditions." },
    ],
  },
  "6": {
    includes: ["Hotel pickup & drop-off", "Local guide", "School visit coordination", "Village walk", "Community introduction"],
    whatToBring: ["Comfortable walking shoes", "Modest clothing", "Camera", "Small donation if desired", "Open mind"],
    itinerary: [
      { time: "Pickup", activity: "Pickup from your hotel and meet your local guide" },
      { time: "School", activity: "Visit a local school and meet nursery students" },
      { time: "Village", activity: "Walk through the village and learn about daily life in Zanzibar" },
      { time: "Return", activity: "Return to your hotel after the cultural experience" },
    ],
    faqs: [
      { q: "Is this a cultural tour?", a: "Yes, this tour is designed to show real local life in Zanzibar with respect and care." },
      { q: "Can I bring donations?", a: "You may bring small donations, but please ask your guide how to give respectfully." },
    ],
  },
  "7": {
    includes: ["Hotel pickup & drop-off", "Jozani Forest guide", "Park entry support", "Mangrove boardwalk", "Drinking water"],
    whatToBring: ["Comfortable shoes", "Insect repellent", "Camera", "Light clothing", "Water"],
    itinerary: [
      { time: "Pickup", activity: "Transfer from your hotel to Jozani Chwaka Bay National Park" },
      { time: "Forest", activity: "Walk through Jozani Forest with your guide" },
      { time: "Wildlife", activity: "Look for the rare Zanzibar red colobus monkey" },
      { time: "Mangroves", activity: "Visit the mangrove boardwalk before returning" },
    ],
    faqs: [
      { q: "What animals can I see?", a: "The main highlight is the rare Zanzibar red colobus monkey." },
      { q: "How long is the walk?", a: "The forest experience is about 2 hours." },
    ],
  },
  "8": {
    includes: ["Hotel pickup & drop-off", "Boat trip from Fumba", "Snorkeling equipment", "Seafood lunch", "Fresh fruit", "Soft drinks"],
    whatToBring: ["Swimwear", "Towel", "Sunscreen", "Hat", "Waterproof bag"],
    itinerary: [
      { time: "Pickup", activity: "Pickup from your hotel and transfer to Fumba fishing village" },
      { time: "Boat", activity: "Board the boat and begin the Safari Blue sea adventure" },
      { time: "Activities", activity: "Swim, snorkel, walk on the island, relax, and shop" },
      { time: "Lunch", activity: "Enjoy seafood lunch, fresh fruit, local music, and dancing" },
    ],
    faqs: [
      { q: "Can I choose private or shared boat?", a: "Yes, Safari Blue can be arranged as a private or shared boat experience." },
      { q: "Is snorkeling equipment included?", a: "Yes, snorkeling equipment is included." },
    ],
  },
  "9": {
    includes: ["Safari planning", "Transport coordination", "Game drive arrangement", "Park lunch support", "Guide support"],
    whatToBring: ["Neutral clothing", "Camera", "Binoculars", "Sunscreen", "Light jacket"],
    itinerary: [
      { time: "Option 1", activity: "One-day safari by plane for a fast wildlife experience" },
      { time: "Option 2", activity: "Two-night, three-day trip by boat with more time on the mainland" },
      { time: "Culture", activity: "Longer trip includes a first-day cultural visit" },
      { time: "Game Drive", activity: "Second day includes full-day game drive by jeep with lunch in the park" },
    ],
    faqs: [
      { q: "Which option is best?", a: "Choose the plane option for speed, or the three-day option for a deeper safari experience." },
      { q: "Is lunch included on safari?", a: "The longer safari includes lunch during the full-day game drive." },
    ],
  },
  "10": {
    includes: ["Boat cruise", "Soft drinks", "Snacks", "Fresh fruit", "Crew support"],
    whatToBring: ["Light jacket", "Camera", "Comfortable clothing", "Sunglasses", "Good mood"],
    itinerary: [
      { time: "4:00 PM", activity: "Start the sunset cruise as the sun begins to lower" },
      { time: "Cruise", activity: "Boat ride along the water with soft drinks, snacks, and fresh fruit" },
      { time: "Sunset", activity: "Watch the sunset over the ocean" },
      { time: "Return", activity: "Return after the evening experience" },
    ],
    faqs: [
      { q: "What time does the cruise start?", a: "The experience starts around 4:00 PM." },
      { q: "Are drinks included?", a: "Yes, soft drinks, snacks, and fresh fruit are included." },
    ],
  },
  "11": {
    includes: ["Hotel pickup & drop-off", "Aquarium visit", "Turtle swimming support", "Local guide", "Visit timing support"],
    whatToBring: ["Swimwear", "Towel", "Camera", "Sunscreen", "Dry clothes"],
    itinerary: [
      { time: "Pickup", activity: "Pickup from your hotel and transfer to Nungwi" },
      { time: "Aquarium", activity: "Visit the natural saltwater swimming pool" },
      { time: "Turtles", activity: "Swim with turtles in a safe and fascinating environment" },
      { time: "Return", activity: "Return to your hotel after the visit" },
    ],
    faqs: [
      { q: "What are the opening hours?", a: "The pool is open from 8:00 AM to 6:30 PM." },
      { q: "Can I take photos?", a: "Yes, the clear water is great for photos." },
    ],
  },
  "12": {
    includes: ["Airport transfers", "Hotel transfers", "Tour transport", "Professional driver", "Multilingual guide support on request"],
    whatToBring: ["Booking details", "Hotel name", "Flight information if needed", "Contact phone", "Luggage details"],
    itinerary: [
      { time: "Booking", activity: "Share pickup point, destination, and preferred time" },
      { time: "Pickup", activity: "Professional driver meets you at the agreed location" },
      { time: "Transfer", activity: "Comfortable transfer with brief local information along the way" },
      { time: "Arrival", activity: "Drop-off at your hotel, airport, tour, or activity location" },
    ],
    faqs: [
      { q: "Do you offer airport pickup?", a: "Yes, we organize transfers from the airport to your hotel." },
      { q: "Which guide languages are available?", a: "Guides can speak English, Italian, French, Spanish, German, and Swahili." },
    ],
  },
};

export const tours: Tour[] = baseTours.map((tour) => ({
  ...tour,
  ...tourDetailContent[tour.id],
}));

export const categories = [
  { name: "Mnemba", icon: "Island", count: 3 },
  { name: "Spice Farm", icon: "Spice", count: 1 },
  { name: "Mnemba Island", icon: "Ocean", count: 1 },
  { name: "Village Tour", icon: "Culture", count: 1 },
  { name: "Jozani Forest", icon: "Forest", count: 1 },
  { name: "Safari Blue", icon: "Boat", count: 1 },
  { name: "Safari Tanzania", icon: "Safari", count: 1 },
  { name: "Sunset Cruise", icon: "Sunset", count: 1 },
  { name: "Aquarium", icon: "Turtles", count: 1 },
  { name: "Transport", icon: "Transfer", count: 1 },
];

export const destinations = [
  {
    name: "Stone Town",
    description: "UNESCO World Heritage Site",
    image: featuredStoneTown,
    tours: 8,
  },
  {
    name: "Nungwi Beach",
    description: "Zanzibar's most vibrant beach village",
    image: featuredNungwi,
    tours: 10,
  },
  {
    name: "Paje",
    description: "Kitesurfing capital of the Indian Ocean",
    image: featuredPaje,
    tours: 6,
  },
  {
    name: "Mnemba Island",
    description: "Pristine marine reserve paradise",
    image: featuredMnembaIsland,
    tours: 4,
  },
  {
    name: "Serengeti",
    description: "Africa's greatest wildlife arena",
    image: featuredSerengeti,
    tours: 5,
  },
  {
    name: "Ngorongoro",
    description: "World's largest intact caldera",
    image: featuredNgorongoro,
    tours: 4,
  },
];



