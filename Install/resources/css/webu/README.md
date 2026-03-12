# Webu — ერთი ფოლდერი, ყველა დიზაინი

აქ არის **ყველა კომპონენტი** თავისი **CSS**-ით და **HTML** მაგალითით.  
**global.css** ყველაფერს აერთიანებს. Webu AI ამ კომპონენტებიდან აკრიბებს თემფლეითებს.

**ბილდერი:** პროექტის ბილდერში ჩანს მხოლოდ ის კომპონენტები, რომლებიც ამ ფოლდერშია. რასაც აქ იცვლი (დიზაინი, ახალი კომპონენტი) — ბილდერი ვიზუალურად იმას აჩვენებს. სია მოდის `config/webu-builder-components.php`-დან; ახალი კომპონენტის დასამატებლად დაამატე ფოლდერი `components/[Name]/` და ჩანაწერი იმ კონფიგში.

## ადმინი — თითო კომპონენტის ვიზუალური ნახვა

ადმინში გახსენი **Component Library** (`/admin/component-library`). იქ ჩანს ყველა კომპონენტი; **Preview** იხსნება ახალ ჩანართში — ვიზუალურად მხოლოდ იმ კომპონენტის ნახვა. დიზაინის შეცვლის შემდეგ განაახლე პრევიუ გვერდი (F5).

## სად ვნახო და სად ვიმუშაო

| რას ცვლი | ფაილის გზა |
|----------|-------------|
| ჰედერი | `components/Header/Header.css` და `Header.html` |
| ჰერო | `components/Hero/Hero.css` და `Hero.html` |
| ბანერი / CTA | `components/Banner/Banner.css` და `Banner.html` |
| პროდუქტის ბარათი | `components/ProductCard/ProductCard.css` და `ProductCard.html` |
| პროდუქტების ბადე | `components/ProductGrid/ProductGrid.css` და `ProductGrid.html` |
| კატეგორიის ბარათი | `components/CategoryCard/CategoryCard.css` და `CategoryCard.html` |
| კატეგორიების სექცია | `components/CategoryGrid/CategoryGrid.css` და `CategoryGrid.html` |
| ფუტერი | `components/Footer/Footer.css` და `Footer.html` |
| კალათა | `components/Cart/Cart.css` და `Cart.html` |
| ნიუსლეტერი | `components/Newsletter/Newsletter.css` და `Newsletter.html` |
| Placeholder | `components/Placeholder/Placeholder.css` და `Placeholder.html` |
| Checkout | `components/Checkout/Checkout.css` და `Checkout.html` |
| პროდუქტის დეტალები | `components/ProductDetails/ProductDetails.css` და `ProductDetails.html` |
| **ყველა კომპონენტი ერთად** | `global.css` |

სრული გზა (პროექტის ფესვიდან): `Install/resources/css/webu/` + ზემოთ მოცემული.  
მაგალითი: `Install/resources/css/webu/components/Header/Header.css`

## სტრუქტურა

```
webu/
├── README.md
├── global.css          ← გლობალი — ყველა კომპონენტის CSS აქ იკრიბება
└── components/
    ├── Header/        ← default, minimal, mega
    ├── Hero/
    ├── Banner/
    ├── ProductCard/   ← classic, minimal, modern, premium, compact
    ├── ProductGrid/
    ├── CategoryCard/
    ├── CategoryGrid/
    ├── Footer/
    ├── Cart/
    ├── Newsletter/
    ├── Placeholder/
    ├── Checkout/
    └── ProductDetails/
```

## როგორ იმუშაო

1. **სტილის შესაცვლელად** — გახსენი `components/[სახელი]/[სახელი].css`.
2. **HTML სტრუქტურის სანახავად** — გახსენი `components/[სახელი]/[სახელი].html`.
3. **გლობალი** — `global.css` იმპორტებს ყველა კომპონენტს; აპლიკაცია იყენებს design-system-ის მეშვეობით.
4. **ახალი კომპონენტის დასამატებლად** — შექმენი `components/[Name]/[Name].css` და `[Name].html`, დაამატე `@import` `global.css`-ში, და დაამატე ჩანაწერი `Install/config/webu-builder-components.php` → `components` მასივში (key, folder, label, category, description).

## ვარიანტები

- **Header:** default, minimal (`.webu-header--minimal`), mega (`.webu-header--mega`)
- **ProductCard:** classic, compact, minimal, modern, premium — ნახე `ProductCard.css` და `ProductCard.html`

## App-თან კავშირი

`design-system/components.css` იმპორტებს `../webu/global.css`-ს.  
React კომპონენტები: `resources/js/ecommerce/components/`.

**შენიშვნა:** HeroBanner იყენებს `.webu-banner` (Banner.css) და `.webu-hero__title` / `.webu-hero__subtitle` (Hero.css) — ორივე ფაილი საჭიროა.
