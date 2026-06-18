## ADDED Requirements

### Requirement: Home page
The storefront home page SHALL render a full-width hero banner, call-to-action buttons (WhatsApp, Browse, Profile), a Featured Collections section listing active categories (2 columns on desktop, 1 on mobile), and a footer with contact details.

#### Scenario: Home lists active categories
- **WHEN** a visitor opens the home page
- **THEN** active categories are displayed as collection cards linking to their category pages

### Requirement: Category page with infinite scroll
The category page SHALL show the category header image, title, and details, followed by its published products listed one per row, loading more on scroll, and end with a grid of all categories.

#### Scenario: Products load and paginate
- **WHEN** a visitor opens a category page
- **THEN** the first page of published products renders, and scrolling loads subsequent pages until exhausted

### Requirement: Product display and actions
Each product SHALL show an image slider (arrows + thumbnails) and three actions: Price (original struck-through when discounted), Inquiry (WhatsApp deep link with image/title/SKU/details), and Order Now (modal with quantity stepper and WhatsApp/Web options).

#### Scenario: Discounted price shown with strike-through
- **WHEN** a product has a discount price
- **THEN** the original price is shown struck-through next to the discounted price

#### Scenario: Inquiry opens WhatsApp with product context
- **WHEN** a visitor taps Inquiry
- **THEN** a WhatsApp link opens prefilled with the product title, SKU, and details

### Requirement: Floating navigation
The storefront SHALL present a floating WhatsApp button (bottom-right) and a floating menu button (bottom-left) that opens a left drawer listing all categories.

#### Scenario: Menu drawer lists categories
- **WHEN** a visitor taps the floating menu button
- **THEN** a left drawer opens listing all active categories as links
