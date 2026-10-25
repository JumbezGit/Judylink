Act as a senior UI/UX designer and React frontend developer. Design and build a complete, professional, responsive tourism website using React and Vite.

Project name: “Hyena Tour & travel”

The website should promote Zanzibar tours, Tanzania safaris, airport transfers, cultural experiences, and beach activities. It must feel modern, trustworthy, adventurous, and premium while remaining easy to use.

## 1. Technology Requirements

Use:

* React with Vite
* JavaScript or TypeScript
* Tailwind CSS
* React Router DOM
* Lucide React icons
* Framer Motion for smooth animations
* Reusable React components
* Responsive layouts for desktop, tablet, and mobile
* Form validation with clear error messages
* Local mock data for tours, bookings, galleries, and testimonials
* Clean, maintainable component structure

Do not create only static Figma screens. Generate functional React components, navigation, forms, interactions, buttons, filters, modals, and responsive layouts.

## 2. Visual Style

Create a premium African travel design inspired by Zanzibar and Tanzania.

Use this color palette:

* Deep ocean blue: #075985
* Turquoise: #0D9488
* Safari gold: #D4A017
* Sand background: #FFF8E7
* Dark text: #172033
* White: #FFFFFF
* Success green: #16A34A
* Error red: #DC2626

Design requirements:

* Large, immersive travel photographs
* Clean white space
* Rounded cards and form controls
* Soft, professional shadows
* Clear visual hierarchy
* Modern typography such as Poppins for headings and Inter for body text
* Smooth hover effects
* Subtle entrance and page-transition animations
* Consistent buttons, spacing, icons, and typography
* Accessible color contrast and keyboard navigation
* Sticky header
* Mobile hamburger navigation
* Floating WhatsApp contact button

## 3. Main Navigation

Create a responsive header containing:

* Logo
* Home
* Tours
* About Us
* Gallery
* Contact Us
* “Book Now” call-to-action button

The active page must be visually highlighted.

Create a complete footer containing:

* Company logo and short description
* Quick navigation links
* Popular tours
* Contact information
* Social media icons
* Newsletter subscription form
* Privacy Policy
* Terms and Conditions
* Copyright information

## 4. Home Page

Build a professional homepage with the following sections:

### Hero Section

* Full-width Zanzibar or safari background image
* Main heading: “Discover Zanzibar and Tanzania With Local Experts”
* Short supporting description
* “Explore Tours” button
* “Plan Your Trip” button
* Tour search box with destination, category, travel date, and guests

### Popular Tours

Display at least six responsive tour cards.

Each card must contain:

* Tour image
* Tour category
* Tour name
* Location
* Duration
* Maximum group size
* Rating and review count
* Starting price
* Short description
* “View Details” button

Example tours:

1. Stone Town and Spice Farm Tour
2. Safari Blue Experience
3. Prison Island and Nakupenda Sandbank
4. Mnemba Island Dolphin and Snorkelling Tour
5. Jozani Forest and The Rock Restaurant
6. Serengeti and Ngorongoro Safari

### Tour Categories

Include categories such as:

* Zanzibar Day Tours
* Beach and Ocean Activities
* Cultural Experiences
* Wildlife Safaris
* Airport Transfers
* Multi-Day Packages

### Why Choose Us

Show benefits with icons:

* Experienced local guides
* Secure booking
* Flexible cancellation
* Affordable prices
* 24/7 customer support
* Custom tour packages

### Featured Destinations

Show visual cards for:

* Stone Town
* Nungwi
* Paje
* Mnemba Island
* Serengeti
* Ngorongoro

### How It Works

Show three simple steps:

1. Select a tour
2. Enter booking details
3. Confirm your adventure

### Testimonials

Create an attractive testimonial slider with:

* Customer photo
* Customer name and country
* Star rating
* Review message
* Tour booked

### Call to Action

Add a strong section inviting visitors to request a customized Zanzibar or Tanzania holiday package.

## 5. Tours Page

Create a responsive tours listing page with:

* Page title and breadcrumb
* Search input
* Destination filter
* Tour-category filter
* Duration filter
* Price-range filter
* Rating filter
* Sort by price, popularity, rating, or newest
* Clear-filters button
* Grid/list view switch
* Results count
* Responsive tour-card grid
* Pagination or “Load More” button
* Friendly empty-results state

Every “View Details” button must open the selected Tour Details page using a dynamic route such as:

`/tours/:tourSlug`

## 6. Tour Details Page

Create a complete, conversion-focused tour details page.

### Tour Header

Include:

* Tour name
* Destination
* Category
* Star rating and review count
* Share button
* Wishlist button

### Image Gallery

Include:

* Large featured image
* Four smaller preview images
* “View All Photos” button
* Full-screen gallery modal or lightbox

### Quick Information

Display:

* Starting price
* Duration
* Group size
* Tour type
* Languages
* Pickup availability
* Difficulty level
* Cancellation policy

### Tour Information

Add separate sections for:

* Tour overview
* Main highlights
* Full itinerary
* Included services
* Excluded services
* What to bring
* Important information
* Meeting and pickup location
* Interactive map placeholder
* Frequently asked questions
* Customer reviews and rating breakdown
* Related tours

### Sticky Booking Card

On desktop, place a sticky booking card beside the tour information. On mobile, display a fixed or easily accessible “Book This Tour” button.

The booking card must include:

* Starting price
* Travel date
* Number of adults
* Number of children
* Optional add-ons
* Estimated total
* “Check Availability” button
* “Book Now” button
* WhatsApp inquiry link

## 7. Multi-Step Booking Form

Create a professional booking page or modal using a progress indicator.

Use the following booking steps:

### Step 1: Select Tour Details

Fields:

* Selected tour
* Travel date
* Preferred starting time
* Number of adults
* Number of children
* Pickup required: Yes or No
* Pickup location or hotel name
* Optional add-ons

Show:

* Availability status
* Price calculation
* “Continue” button

### Step 2: Customer Information

Fields:

* First name
* Last name
* Email address
* Phone number with country code
* Nationality
* Country of residence
* Preferred contact method
* Special requests
* Accessibility or dietary requirements

All required fields must have validation.

### Step 3: Review Booking

Display a complete booking summary:

* Tour name and image
* Tour date and time
* Number of travellers
* Pickup information
* Customer information
* Selected add-ons
* Subtotal
* Taxes or fees
* Discount
* Final total

Allow the customer to edit information from previous steps.

Include:

* Promo-code field
* Terms and conditions checkbox
* Cancellation-policy checkbox

### Step 4: Payment

Create a secure-looking payment interface with options for:

* Credit or debit card
* Mobile money
* Bank transfer
* PayPal
* Pay later or manual confirmation

This is a frontend prototype, so do not process real payments. Use simulated payment behaviour and clearly structure it for future payment API integration.

Include:

* Payment method selection
* Payment summary
* Secure-payment indicators
* Billing address, if required
* “Confirm and Pay” button
* Loading state
* Success and error states

### Step 5: Booking Confirmation

After successful submission, show:

* Success icon and confirmation message
* Unique booking reference, such as `ZTS-2026-00125`
* Tour name
* Booking date
* Travel date
* Number of guests
* Total amount
* Payment status
* Customer email
* “Download Booking Voucher” button
* “Print Confirmation” button
* “Return to Home” button
* “View More Tours” button

Add a message informing the customer that the booking confirmation has been sent to their email and WhatsApp.

The booking form must:

* Preserve information between steps
* Prevent users from proceeding when required fields are missing
* Show previous and next buttons
* Show the current step clearly
* Calculate prices dynamically
* Work perfectly on mobile devices
* Provide a clear final confirmation
* Use a booking context or state-management solution

## 8. About Us Page

Include:

* About hero section
* Company story
* Mission
* Vision
* Core values
* Why travel with us
* Company statistics
* Local guide/team cards
* Responsible tourism commitments
* Safety and service standards
* Partner or certification logos
* Call-to-action section

Emphasize local knowledge, reliability, personalized service, responsible tourism, and authentic Zanzibar experiences.

## 9. Gallery Page

Create a modern travel gallery containing:

* Page heading and introduction
* Category filters
* Masonry or responsive grid
* Categories such as Beaches, Safaris, Culture, Activities, Guests, and Hotels
* Hover overlays
* Image titles and locations
* Full-screen lightbox
* Previous and next image controls
* Video gallery section
* Instagram-style call-to-action

## 10. Contact Us Page

Create a complete contact page with:

* Contact introduction
* Company address
* Phone number
* WhatsApp number
* Email address
* Business hours
* Social media links
* Map placeholder
* Emergency contact information

Create a validated contact form containing:

* Full name
* Email
* Phone number
* Country
* Subject
* Type of inquiry
* Travel date
* Number of travellers
* Message
* Consent checkbox
* Submit button

Include loading, success, and error states.

Also add:

* Frequently asked questions
* “Chat on WhatsApp” button
* Newsletter signup
* Response-time information

## 11. Additional Features

Include:

* Scroll-to-top button
* Loading skeletons
* Toast notifications
* 404 Not Found page
* SEO-friendly page titles and descriptions
* Breadcrumb navigation
* Wishlist functionality
* Recently viewed tours
* Currency selector for USD, EUR, GBP, and TZS
* Language selector for English and Swahili
* Cookie-consent banner
* Privacy Policy page
* Terms and Conditions page
* Cancellation Policy page
* Tour inquiry modal
* Empty, loading, success, and error states
* Reusable confirmation dialog
* Responsive image optimization

## 12. Responsive Behaviour

Design all screens for:

* Mobile: 320px–767px
* Tablet: 768px–1023px
* Desktop: 1024px and above
* Large desktop: 1440px and above

Mobile requirements:

* Collapsible navigation menu
* Touch-friendly controls
* Single-column forms and cards
* Sticky booking call-to-action
* Readable typography without zooming
* No horizontal scrolling
* Optimized image sizes
* Booking progress indicator adapted for small screens

## 13. Suggested React Structure

Organize the project using reusable components and clear folders:

* `components`
* `pages`
* `layouts`
* `routes`
* `data`
* `hooks`
* `context`
* `services`
* `utils`
* `assets`

Create reusable components such as:

* Header
* Footer
* Hero
* TourCard
* TourFilters
* SearchBar
* GalleryGrid
* TestimonialCard
* BookingStepper
* BookingSummary
* PriceCalculator
* ContactForm
* NewsletterForm
* LoadingSpinner
* EmptyState
* ErrorState
* ConfirmationModal
* WhatsAppButton

## 14. Final Output

Generate:

1. A complete responsive UI design
2. Functional React and Vite frontend code
3. All five primary pages
4. Dynamic Tour Details pages
5. A complete five-step booking form
6. Reusable components
7. Responsive navigation and footer
8. Realistic tourism content and placeholder images
9. Working filtering, validation, price calculation, and interactions
10. Clean code ready for future backend and payment API integration

Do not leave sections incomplete. Do not use generic labels such as “Lorem Ipsum.” Use realistic Zanzibar and Tanzania tourism content throughout the website.
