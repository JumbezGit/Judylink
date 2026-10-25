<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

$publicGalleryBase = rtrim(PUBLIC_UPLOAD_BASE, '/') . '/gallery';

function gallery_image(string $fileName): string
{
    global $publicGalleryBase;
    return $publicGalleryBase . '/' . rawurlencode($fileName);
}

function default_tour(array $tour): array
{
    $tour += [
        'group_size' => $tour['category'] === 'Transport' ? 'Private' : 'Private or group',
        'rating' => 4.9,
        'currency' => 'USD',
        'includes' => ['Hotel pickup & drop-off', 'Professional guide', 'Transportation', 'Drinking water'],
        'excludes' => ['Personal expenses', 'Gratuities', 'Extra activities not listed'],
        'what_to_bring' => ['Comfortable clothing', 'Sunscreen', 'Camera', 'Cash for personal expenses'],
        'difficulty' => 'Easy',
        'languages' => ['English', 'Italian', 'French', 'Spanish', 'German', 'Swahili'],
        'pickup' => true,
        'cancellation' => 'Free cancellation up to 24 hours before',
        'faqs' => [
            ['q' => 'Is hotel pickup included?', 'a' => 'Yes, transportation from your hotel is included unless a custom meeting point is arranged.'],
            ['q' => 'Can this tour be private?', 'a' => 'Yes, we can arrange this experience as a private tour or a group tour.'],
        ],
        'tags' => [strtolower($tour['category']), strtolower($tour['location'])],
    ];

    if (empty($tour['itinerary'])) {
        $tour['itinerary'] = [
            ['time' => 'Start', 'activity' => 'Pickup from your hotel and introduction from your guide'],
            ['time' => 'Tour', 'activity' => $tour['highlights'][0]],
            ['time' => 'Experience', 'activity' => $tour['highlights'][1] ?? 'Enjoy the main tour experience'],
            ['time' => 'Return', 'activity' => 'Drop-off at your hotel after the tour'],
        ];
    }

    $tour['image_path'] = $tour['gallery'][0];
    return $tour;
}

$tours = [
    default_tour([
        'slug' => 'prison-island',
        'name' => 'Prison Island',
        'category' => 'Mnemba',
        'location' => 'Prison Island, Zanzibar',
        'duration' => 'Half Day',
        'review_count' => 33,
        'price' => 45,
        'description' => "Prison Island is home to giant tortoises and has a fascinating history. This half-day tour includes an excellent knowledgeable guide, optional swimming around the island, and hotel transportation from anywhere you are staying. The boat ride from Stone Town to Prison Island takes about 30 minutes each way. Distance is not an issue - Hakuna Matata!",
        'highlights' => ['Meet the giant tortoises of Prison Island', "Learn the island's fascinating history", 'Enjoy an optional swim around the island', 'Travel by boat from Stone Town'],
        'gallery' => array_map('gallery_image', ['prison_island_tours-in-zanzibar.jpg', 'zanzibar.jpg', 'Mnemba-Island.jpg', 'Nungwi.jpg', 'Forodhani-Night-Market-Stone-Town-Zanzibar.jpg']),
        'includes' => ['Hotel pickup & drop-off', 'Return boat from Stone Town', 'Professional guide', 'Prison Island entry support', 'Drinking water'],
        'what_to_bring' => ['Swimwear', 'Towel', 'Sunscreen', 'Camera', 'Comfortable sandals'],
        'itinerary' => [
            ['time' => 'Pickup', 'activity' => 'Pickup from your hotel anywhere in Zanzibar'],
            ['time' => 'Boat Ride', 'activity' => 'Travel from Stone Town to Prison Island by boat, about 30 minutes each way'],
            ['time' => 'Island Visit', 'activity' => "Meet the giant tortoises and learn the island's history with your guide"],
            ['time' => 'Swim', 'activity' => 'Enjoy optional swimming around the island when sea conditions allow'],
            ['time' => 'Return', 'activity' => 'Boat back to Stone Town and transfer to your hotel'],
        ],
        'faqs' => [
            ['q' => 'How long is the boat ride?', 'a' => 'The boat ride from Stone Town to Prison Island takes about 30 minutes each way.'],
            ['q' => 'Can I swim on this tour?', 'a' => 'Yes, swimming around the island is possible when the weather and sea conditions are suitable.'],
        ],
    ]),
    default_tour([
        'slug' => 'nakupenda-sandbank-picnic',
        'name' => 'Nakupenda Sandbank Picnic',
        'category' => 'Mnemba',
        'location' => 'Nakupenda Sandbank, Zanzibar',
        'duration' => 'Half Day',
        'review_count' => 42,
        'price' => 55,
        'description' => 'This is a great place to relax, chill, and swim. The tour includes a seafood lunch with fresh fruit on a beautiful sandbank in the middle of the ocean, making it perfect for picnics, wedding celebrations, or engagement proposals. We organize everything for you.',
        'highlights' => ['Relax and swim on Nakupenda Sandbank', 'Enjoy seafood lunch and fresh fruit', 'Plan a picnic, wedding celebration, or proposal', 'Spend time on a sandbank in the ocean'],
        'gallery' => array_map('gallery_image', ['zanzibar.jpg', 'chair-table-dinning-beach-sea-with-blue-sky.jpg', 'Mnemba2.jpg', 'Nungwi12.jpg', 'Michamvi.jpg']),
        'includes' => ['Hotel pickup & drop-off', 'Boat transfer', 'Seafood lunch', 'Fresh fruit', 'Soft drinks'],
        'what_to_bring' => ['Swimwear', 'Towel', 'Sunscreen', 'Sunglasses', 'Waterproof bag'],
        'itinerary' => [
            ['time' => 'Pickup', 'activity' => 'Pickup from your hotel and transfer to the departure point'],
            ['time' => 'Boat', 'activity' => 'Cruise to Nakupenda Sandbank in the middle of the ocean'],
            ['time' => 'Picnic', 'activity' => 'Relax, swim, take photos, and enjoy a seafood picnic with fresh fruit'],
            ['time' => 'Return', 'activity' => 'Return by boat and transfer back to your hotel'],
        ],
        'faqs' => [
            ['q' => 'Is lunch included?', 'a' => 'Yes, a seafood lunch with fresh fruit is included.'],
            ['q' => 'Can you organize a celebration?', 'a' => 'Yes, we can arrange picnics, wedding celebrations, and engagement proposals.'],
        ],
    ]),
    default_tour([
        'slug' => 'stone-town-tour',
        'name' => 'Stone Town Tour',
        'category' => 'Mnemba',
        'location' => 'Stone Town, Zanzibar',
        'duration' => '2 Hours',
        'review_count' => 51,
        'price' => 30,
        'description' => 'You cannot visit Zanzibar without exploring Stone Town, a key historical site that tells the story of colonialism, sultans, and the slave trade. You will also see the house where Freddie Mercury was born and learn about his history and why he was born in Zanzibar. Our experienced guide will share the important stories of Stone Town.',
        'highlights' => ['Explore the history of Stone Town', 'Learn about sultans, colonialism, and the slave trade', 'Visit the Freddie Mercury house area', 'Walk with an experienced local guide'],
        'gallery' => array_map('gallery_image', ['Forodhani-Night-Market-Stone-Town-Zanzibar.jpg', 'zanzibar-old-slave-market-anglican-cathedral-tanzania-1536x1075.jpg', 'zanzibar.jpg', 'kizimbani.jpg', 'rowen-smith-NMcvybDrCYY-unsplash-1536x1024.jpg']),
        'includes' => ['Experienced walking guide', 'Stone Town history tour', 'Freddie Mercury house area visit', 'Slave trade history briefing', 'Drinking water'],
        'what_to_bring' => ['Comfortable walking shoes', 'Modest clothing', 'Camera', 'Sun hat', 'Cash for personal shopping'],
        'itinerary' => [
            ['time' => 'Start', 'activity' => 'Meet your guide in Stone Town or arrange pickup'],
            ['time' => 'History Walk', 'activity' => 'Explore stories of sultans, colonialism, and the slave trade'],
            ['time' => 'Landmarks', 'activity' => 'Visit historic streets, markets, and the Freddie Mercury house area'],
            ['time' => 'Finish', 'activity' => 'End with local recommendations from your guide'],
        ],
        'faqs' => [
            ['q' => 'How long is the Stone Town tour?', 'a' => 'The walking tour takes about 2 hours.'],
            ['q' => 'Is this tour suitable for children?', 'a' => 'Yes, it is an easy walking tour and can be adapted for families.'],
        ],
    ]),
    default_tour([
        'slug' => 'spice-farm-tour',
        'name' => 'Spice Farm Tour',
        'category' => 'Spice Farm',
        'location' => 'Zanzibar Spice Farms',
        'duration' => '2 Hours',
        'review_count' => 60,
        'price' => 35,
        'description' => "Zanzibar is famous for its naturally fragrant and flavorful spices. On this walking tour, you will learn how spice trees are grown and how their fruits are produced. After the tour, you can visit a spice shop and purchase spices if you wish. Do not miss this opportunity - let's go together and experience the spice farms!",
        'highlights' => ['Walk through a Zanzibar spice farm', 'Learn how spices grow naturally', 'Taste and smell fresh island spices', 'Visit a spice shop after the tour'],
        'gallery' => array_map('gallery_image', ['kizimbani.jpg', 'zanzibar-old-slave-market-anglican-cathedral-tanzania-1536x1075.jpg', 'Forodhani-Night-Market-Stone-Town-Zanzibar.jpg', 'zanzibar.jpg', 'rowen-smith-NMcvybDrCYY-unsplash-1536x1024.jpg']),
        'includes' => ['Hotel pickup & drop-off', 'Spice farm guide', 'Spice tasting', 'Fruit tasting', 'Spice shop visit'],
        'what_to_bring' => ['Comfortable shoes', 'Light clothing', 'Sunscreen', 'Camera', 'Cash if you want to buy spices'],
        'itinerary' => [
            ['time' => 'Pickup', 'activity' => 'Transfer from your hotel to the spice farm'],
            ['time' => 'Walk', 'activity' => 'Walk through the farm and learn how spice trees grow'],
            ['time' => 'Tasting', 'activity' => 'Smell, taste, and identify Zanzibar spices and tropical fruits'],
            ['time' => 'Shop', 'activity' => 'Optional visit to a spice shop before returning to your hotel'],
        ],
        'faqs' => [
            ['q' => 'Can I buy spices?', 'a' => 'Yes, there is an optional spice shop visit after the walking tour.'],
            ['q' => 'How long is the tour?', 'a' => 'The guided spice farm walk takes about 2 hours.'],
        ],
    ]),
    default_tour([
        'slug' => 'mnemba-island-tour',
        'name' => 'Mnemba Island Tour',
        'category' => 'Mnemba Island',
        'location' => 'Mnemba Island, Zanzibar',
        'duration' => 'Half Day',
        'review_count' => 69,
        'price' => 75,
        'description' => 'Mnemba is a private island off the coast of Zanzibar, reached by a short boat ride. On this tour, we go swimming with dolphins and snorkeling in one of the best spots in Zanzibar. We do not dock on the island because it is private. Seafood lunch and soft drinks are provided.',
        'highlights' => ['Snorkel near Mnemba Island', 'Swim with dolphins when conditions allow', 'Enjoy seafood lunch and soft drinks', "Travel by boat along Zanzibar's coast"],
        'gallery' => array_map('gallery_image', ['Mnemba-Island.jpg', 'Mnemba.jpg', 'Mnemba2.jpg', 'Mnemba3.jpg', 'Mnemba4.jpg']),
        'includes' => ['Hotel pickup & drop-off', 'Boat transfer', 'Snorkeling equipment', 'Seafood lunch', 'Soft drinks'],
        'what_to_bring' => ['Swimwear', 'Towel', 'Reef-safe sunscreen', 'Waterproof phone pouch', 'Underwater camera'],
        'itinerary' => [
            ['time' => 'Pickup', 'activity' => 'Pickup from your hotel and transfer to the beach departure point'],
            ['time' => 'Boat', 'activity' => 'Take a short boat ride toward Mnemba Island'],
            ['time' => 'Ocean', 'activity' => 'Swim with dolphins when possible and snorkel in clear waters'],
            ['time' => 'Lunch', 'activity' => 'Enjoy seafood lunch and soft drinks before returning'],
        ],
        'faqs' => [
            ['q' => 'Do we dock on Mnemba Island?', 'a' => 'No, Mnemba is private, so the tour stays in the surrounding ocean and snorkeling areas.'],
            ['q' => 'Are dolphins guaranteed?', 'a' => 'Dolphins are wild animals, so sightings depend on natural conditions.'],
        ],
    ]),
    default_tour([
        'slug' => 'village-tour',
        'name' => 'Village Tour',
        'category' => 'Village Tour',
        'location' => 'Local Village, Zanzibar',
        'duration' => 'Half Day',
        'review_count' => 78,
        'price' => 40,
        'description' => "This tour offers a glimpse into the real life of Zanzibar. We visit a local school, meet nursery students, and walk through a village to observe daily life. Despite economic challenges, people live in harmony, depending on their faith and enjoying a peaceful life. Let's go together and experience the life of Zanzibar.",
        'highlights' => ['Visit a local school', 'Meet nursery students', 'Walk through a Zanzibar village', 'Learn about local daily life'],
        'gallery' => array_map('gallery_image', ['rowen-smith-NMcvybDrCYY-unsplash-1536x1024.jpg', 'zanzibar.jpg', 'kizimbani.jpg', 'Nungwi1.jpg', 'Forodhani-Night-Market-Stone-Town-Zanzibar.jpg']),
        'includes' => ['Hotel pickup & drop-off', 'Local guide', 'School visit coordination', 'Village walk', 'Community introduction'],
        'what_to_bring' => ['Comfortable walking shoes', 'Modest clothing', 'Camera', 'Small donation if desired', 'Open mind'],
        'itinerary' => [
            ['time' => 'Pickup', 'activity' => 'Pickup from your hotel and meet your local guide'],
            ['time' => 'School', 'activity' => 'Visit a local school and meet nursery students'],
            ['time' => 'Village', 'activity' => 'Walk through the village and learn about daily life in Zanzibar'],
            ['time' => 'Return', 'activity' => 'Return to your hotel after the cultural experience'],
        ],
        'faqs' => [
            ['q' => 'Is this a cultural tour?', 'a' => 'Yes, this tour is designed to show real local life in Zanzibar with respect and care.'],
            ['q' => 'Can I bring donations?', 'a' => 'You may bring small donations, but please ask your guide how to give respectfully.'],
        ],
    ]),
    default_tour([
        'slug' => 'jozani-forest-tour',
        'name' => 'Jozani Forest Tour',
        'category' => 'Jozani Forest',
        'location' => 'Jozani Chwaka Bay National Park',
        'duration' => '2 Hours',
        'review_count' => 87,
        'price' => 45,
        'description' => 'Jozani Forest, also known as Jozani Chwaka Bay National Park, is the only national park on Zanzibar Island. The forest is home to the rare red colobus monkey, which can only be found in Zanzibar. During the two-hour walk, you will also see mangrove trees.',
        'highlights' => ['See the rare Zanzibar red colobus monkey', 'Walk through Jozani Forest', 'Explore the mangrove boardwalk', "Visit Zanzibar's only national park"],
        'gallery' => array_map('gallery_image', ['Jozani.jpg', 'Jozani2.jpg', 'Jozani3.jpg', 'chumbe.jpg', 'breathtaking-view-trees-foggy-hills-captured-sunset-hawke-s-bay-new-zealand.jpg']),
        'includes' => ['Hotel pickup & drop-off', 'Jozani Forest guide', 'Park entry support', 'Mangrove boardwalk', 'Drinking water'],
        'what_to_bring' => ['Comfortable shoes', 'Insect repellent', 'Camera', 'Light clothing', 'Water'],
        'itinerary' => [
            ['time' => 'Pickup', 'activity' => 'Transfer from your hotel to Jozani Chwaka Bay National Park'],
            ['time' => 'Forest', 'activity' => 'Walk through Jozani Forest with your guide'],
            ['time' => 'Wildlife', 'activity' => 'Look for the rare Zanzibar red colobus monkey'],
            ['time' => 'Mangroves', 'activity' => 'Visit the mangrove boardwalk before returning'],
        ],
        'faqs' => [
            ['q' => 'What animals can I see?', 'a' => 'The main highlight is the rare Zanzibar red colobus monkey.'],
            ['q' => 'How long is the walk?', 'a' => 'The forest experience is about 2 hours.'],
        ],
    ]),
    default_tour([
        'slug' => 'safari-blue-tour',
        'name' => 'Safari Blue Tour',
        'category' => 'Safari Blue',
        'location' => 'Fumba, Zanzibar',
        'duration' => 'Full Day',
        'review_count' => 96,
        'price' => 80,
        'description' => "Safari Blue is Zanzibar's most talked-about sea adventure. This full-day excursion departs from the fishing village of Fumba and includes seafood lunch, fresh fruit, swimming, snorkeling, island walking, relaxing, shopping, and local music and dancing. You can choose a private or shared boat. Hakuna Matata!",
        'highlights' => ['Depart by boat from Fumba village', 'Swim and snorkel in clear ocean water', 'Enjoy seafood lunch and fresh fruit', 'Experience local music and dancing'],
        'gallery' => array_map('gallery_image', ['safariblue.webp', 'Mnemba5.jpg', 'Mnemba6.jpg', 'Nungwi8.jpg', 'zanzibar-island-kizimkazi-beach-the-residence-hotel-orig.jpg']),
        'includes' => ['Hotel pickup & drop-off', 'Boat trip from Fumba', 'Snorkeling equipment', 'Seafood lunch', 'Fresh fruit', 'Soft drinks'],
        'what_to_bring' => ['Swimwear', 'Towel', 'Sunscreen', 'Hat', 'Waterproof bag'],
        'itinerary' => [
            ['time' => 'Pickup', 'activity' => 'Pickup from your hotel and transfer to Fumba fishing village'],
            ['time' => 'Boat', 'activity' => 'Board the boat and begin the Safari Blue sea adventure'],
            ['time' => 'Activities', 'activity' => 'Swim, snorkel, walk on the island, relax, and shop'],
            ['time' => 'Lunch', 'activity' => 'Enjoy seafood lunch, fresh fruit, local music, and dancing'],
        ],
        'faqs' => [
            ['q' => 'Can I choose private or shared boat?', 'a' => 'Yes, Safari Blue can be arranged as a private or shared boat experience.'],
            ['q' => 'Is snorkeling equipment included?', 'a' => 'Yes, snorkeling equipment is included.'],
        ],
    ]),
    default_tour([
        'slug' => 'safari-tanzania-tour',
        'name' => 'Safari Tanzania Tour',
        'category' => 'Safari Tanzania',
        'location' => 'Mainland Tanzania',
        'duration' => '1 Day or 3 Days',
        'review_count' => 105,
        'price' => 350,
        'description' => 'Go on safari in Tanzania and experience wild animals in their natural habitat. We offer two options: a one-day trip by plane or a two-night, three-day trip by boat. Both options are safe and peaceful. The longer trip includes a cultural visit on the first day and a full-day game drive with lunch in the park on the second day.',
        'highlights' => ['See wild animals in their natural habitat', 'Choose a one-day flight safari or three-day trip', 'Enjoy a full-day jeep game drive', 'Have lunch inside the park'],
        'gallery' => array_map('gallery_image', ['elephant-herd-against-sunset.jpg', 'giraffe-wild.jpg', 'beautiful-zebra-wild.jpg', 'view-wild-rhino-nature.jpg', 'Kilimanjaro.jpg']),
        'includes' => ['Safari planning', 'Transport coordination', 'Game drive arrangement', 'Park lunch support', 'Guide support'],
        'what_to_bring' => ['Neutral clothing', 'Camera', 'Binoculars', 'Sunscreen', 'Light jacket'],
        'itinerary' => [
            ['time' => 'Option 1', 'activity' => 'One-day safari by plane for a fast wildlife experience'],
            ['time' => 'Option 2', 'activity' => 'Two-night, three-day trip by boat with more time on the mainland'],
            ['time' => 'Culture', 'activity' => 'Longer trip includes a first-day cultural visit'],
            ['time' => 'Game Drive', 'activity' => 'Second day includes full-day game drive by jeep with lunch in the park'],
        ],
        'faqs' => [
            ['q' => 'Which option is best?', 'a' => 'Choose the plane option for speed, or the three-day option for a deeper safari experience.'],
            ['q' => 'Is lunch included on safari?', 'a' => 'The longer safari includes lunch during the full-day game drive.'],
        ],
    ]),
    default_tour([
        'slug' => 'sunset-cruise-tour',
        'name' => 'Sunset Cruise Tour',
        'category' => 'Sunset Cruise',
        'location' => 'Zanzibar Coast',
        'duration' => 'Evening',
        'review_count' => 114,
        'price' => 45,
        'description' => 'We start this experience around 4:00 PM as the sun begins to set. We take a boat out to enjoy the beautiful sunset over the water. Onboard, we provide soft drinks, snacks, and fresh fruit for a relaxed evening experience.',
        'highlights' => ['Cruise along the coast around sunset', 'Enjoy soft drinks, snacks, and fresh fruit', 'Watch the sun set over the water', 'Relax on a peaceful boat experience'],
        'gallery' => array_map('gallery_image', ['Nungwi15.jpg', 'Nungwi14.jpg', 'Nungwi13.jpg', 'Michamvi.jpg', 'zanzibar-island-kizimkazi-beach-the-residence-hotel-orig.jpg']),
        'includes' => ['Boat cruise', 'Soft drinks', 'Snacks', 'Fresh fruit', 'Crew support'],
        'what_to_bring' => ['Light jacket', 'Camera', 'Comfortable clothing', 'Sunglasses', 'Good mood'],
        'itinerary' => [
            ['time' => '4:00 PM', 'activity' => 'Start the sunset cruise as the sun begins to lower'],
            ['time' => 'Cruise', 'activity' => 'Boat ride along the water with soft drinks, snacks, and fresh fruit'],
            ['time' => 'Sunset', 'activity' => 'Watch the sunset over the ocean'],
            ['time' => 'Return', 'activity' => 'Return after the evening experience'],
        ],
        'faqs' => [
            ['q' => 'What time does the cruise start?', 'a' => 'The experience starts around 4:00 PM.'],
            ['q' => 'Are drinks included?', 'a' => 'Yes, soft drinks, snacks, and fresh fruit are included.'],
        ],
    ]),
    default_tour([
        'slug' => 'aquarium',
        'name' => 'Aquarium',
        'category' => 'Aquarium',
        'location' => 'Nungwi, Zanzibar',
        'duration' => 'Flexible Visit',
        'review_count' => 123,
        'price' => 25,
        'description' => 'This natural swimming pool is located in Nungwi in northern Zanzibar. You can swim with turtles in a safe and fascinating saltwater environment connected to the ocean. The clear water makes for stunning photos. The pool is open from 8:00 AM to 6:30 PM.',
        'highlights' => ['Swim with turtles in Nungwi', 'Visit a natural saltwater pool', 'Take beautiful photos in clear water', 'Enjoy a flexible daytime activity'],
        'gallery' => array_map('gallery_image', ['Nungwi10.jpg', 'Nungwi11.jpg', 'Nungwi12.jpg', 'Nungwi.jpg', 'nugwi_old.jpg']),
        'includes' => ['Hotel pickup & drop-off', 'Aquarium visit', 'Turtle swimming support', 'Local guide', 'Visit timing support'],
        'what_to_bring' => ['Swimwear', 'Towel', 'Camera', 'Sunscreen', 'Dry clothes'],
        'itinerary' => [
            ['time' => 'Pickup', 'activity' => 'Pickup from your hotel and transfer to Nungwi'],
            ['time' => 'Aquarium', 'activity' => 'Visit the natural saltwater swimming pool'],
            ['time' => 'Turtles', 'activity' => 'Swim with turtles in a safe and fascinating environment'],
            ['time' => 'Return', 'activity' => 'Return to your hotel after the visit'],
        ],
        'faqs' => [
            ['q' => 'What are the opening hours?', 'a' => 'The pool is open from 8:00 AM to 6:30 PM.'],
            ['q' => 'Can I take photos?', 'a' => 'Yes, the clear water is great for photos.'],
        ],
    ]),
    default_tour([
        'slug' => 'transport',
        'name' => 'Transport',
        'category' => 'Transport',
        'location' => 'Zanzibar',
        'duration' => 'Flexible',
        'review_count' => 132,
        'price' => 20,
        'description' => 'We organize airport-to-hotel transfers, tour transfers, and transportation for other activities. Our professional drivers take you to your destination while sharing brief local information. We also have multilingual tour guides who speak English, Italian, French, Spanish, and German.',
        'highlights' => ['Book airport and hotel transfers', 'Arrange transport for tours and activities', 'Travel with professional local drivers', 'Request multilingual guide support'],
        'gallery' => array_map('gallery_image', ['pexels-droneafrica-17732689.jpg', 'zanzibar.jpg', 'Forodhani-Night-Market-Stone-Town-Zanzibar.jpg', 'Nungwi1.jpg', 'Michamvi.jpg']),
        'includes' => ['Airport transfers', 'Hotel transfers', 'Tour transport', 'Professional driver', 'Multilingual guide support on request'],
        'what_to_bring' => ['Booking details', 'Hotel name', 'Flight information if needed', 'Contact phone', 'Luggage details'],
        'itinerary' => [
            ['time' => 'Booking', 'activity' => 'Share pickup point, destination, and preferred time'],
            ['time' => 'Pickup', 'activity' => 'Professional driver meets you at the agreed location'],
            ['time' => 'Transfer', 'activity' => 'Comfortable transfer with brief local information along the way'],
            ['time' => 'Arrival', 'activity' => 'Drop-off at your hotel, airport, tour, or activity location'],
        ],
        'faqs' => [
            ['q' => 'Do you offer airport pickup?', 'a' => 'Yes, we organize transfers from the airport to your hotel.'],
            ['q' => 'Which guide languages are available?', 'a' => 'Guides can speak English, Italian, French, Spanish, German, and Swahili.'],
        ],
    ]),
];

$pdo = Database::pdo();
$requestedSlug = trim((string) ($argv[1] ?? ''));
$siteStmt = $requestedSlug !== ''
    ? $pdo->prepare('SELECT id FROM company_sites WHERE site_slug = ? LIMIT 1')
    : $pdo->prepare('SELECT id FROM company_sites ORDER BY id LIMIT 1');
$siteStmt->execute($requestedSlug !== '' ? [$requestedSlug] : []);
$siteId = (int) ($siteStmt->fetchColumn() ?: 0);
if ($siteId < 1) {
    fwrite(STDERR, "Site not found. Pass a valid site slug as the first argument." . PHP_EOL);
    exit(1);
}
$sql = <<<'SQL'
INSERT INTO tours (
  site_id, slug, name, category, location, duration, group_size, rating, review_count, price, currency,
  description, highlights, includes, excludes, what_to_bring, difficulty, languages, pickup,
  cancellation, image_path, gallery, itinerary, faqs, tags, is_active, display_order
) VALUES (
  :site_id, :slug, :name, :category, :location, :duration, :group_size, :rating, :review_count, :price, :currency,
  :description, :highlights, :includes, :excludes, :what_to_bring, :difficulty, :languages, :pickup,
  :cancellation, :image_path, :gallery, :itinerary, :faqs, :tags, 1, :display_order
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  category = VALUES(category),
  location = VALUES(location),
  duration = VALUES(duration),
  group_size = VALUES(group_size),
  rating = VALUES(rating),
  review_count = VALUES(review_count),
  price = VALUES(price),
  currency = VALUES(currency),
  description = VALUES(description),
  highlights = VALUES(highlights),
  includes = VALUES(includes),
  excludes = VALUES(excludes),
  what_to_bring = VALUES(what_to_bring),
  difficulty = VALUES(difficulty),
  languages = VALUES(languages),
  pickup = VALUES(pickup),
  cancellation = VALUES(cancellation),
  image_path = VALUES(image_path),
  gallery = VALUES(gallery),
  itinerary = VALUES(itinerary),
  faqs = VALUES(faqs),
  tags = VALUES(tags),
  is_active = 1,
  display_order = VALUES(display_order)
SQL;

$stmt = $pdo->prepare($sql);
$upserted = 0;

foreach ($tours as $index => $tour) {
    $stmt->execute([
        'site_id' => $siteId,
        'slug' => $tour['slug'],
        'name' => $tour['name'],
        'category' => $tour['category'],
        'location' => $tour['location'],
        'duration' => $tour['duration'],
        'group_size' => $tour['group_size'],
        'rating' => $tour['rating'],
        'review_count' => $tour['review_count'],
        'price' => $tour['price'],
        'currency' => $tour['currency'],
        'description' => $tour['description'],
        'highlights' => json_encode($tour['highlights'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'includes' => json_encode($tour['includes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'excludes' => json_encode($tour['excludes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'what_to_bring' => json_encode($tour['what_to_bring'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'difficulty' => $tour['difficulty'],
        'languages' => json_encode($tour['languages'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'pickup' => $tour['pickup'] ? 1 : 0,
        'cancellation' => $tour['cancellation'],
        'image_path' => $tour['image_path'],
        'gallery' => json_encode($tour['gallery'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'itinerary' => json_encode($tour['itinerary'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'faqs' => json_encode($tour['faqs'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'tags' => json_encode($tour['tags'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'display_order' => ($index + 1) * 10,
    ]);
    $upserted++;
}

echo "Tours upserted: {$upserted}" . PHP_EOL;
echo "No existing tours were deleted or disabled." . PHP_EOL;
