import {
    createContext,
    useContext,
    useCallback,
    useEffect,
    useMemo,
    ReactNode,
} from 'react';
import { usePage, router } from '@inertiajs/react';

interface Language {
    code: string;
    country_code: string;
    name: string;
    native_name: string;
    is_rtl: boolean;
}

interface LocaleData {
    current: string;
    isRtl: boolean;
    available: Language[];
}

interface LanguageContextType {
    locale: string;
    isRtl: boolean;
    availableLanguages: Language[];
    setLocale: (locale: string) => void;
    t: (key: string, replacements?: Record<string, string | number>) => string;
}

const LanguageContext = createContext<LanguageContextType | undefined>(
    undefined
);

const STORAGE_KEY = 'app-locale';
const RTL_STORAGE_KEY = 'app-locale-rtl';

interface LanguageProviderProps {
    children: ReactNode;
}

const KA_EXACT_FALLBACK_TRANSLATIONS: Record<string, string> = {
    'New chat': 'ახალი ჩატი',
    'New project': 'ახალი პროექტი',
    'Create new project': 'შექმენი ახალი პროექტი',
    'What kind of website do you want to create?': 'როგორი ვებსაიტის შექმნა გინდა?',
    'Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".': 'ბილდერი გამორთულია. ჩართეთ: composer dev ან bash scripts/start-local-builder.sh',
    'More': 'მეტი',
    // Sidebar menu labels
    'All Projects': 'ყველა პროექტი',
    'File Manager': 'ფაილების მენეჯერი',
    'Files': 'ფაილები',
    'Database': 'მონაცემთა ბაზა',
    'Billing': 'ბილინგი',
    'Settings': 'პარამეტრები',
    'Overview': 'მიმოხილვა',
    'Users': 'მომხმარებლები',
    'Tenants': 'ტენანტები',
    'Subscriptions': 'გამოწერები',
    'Transactions': 'ტრანზაქციები',
    'Referrals': 'რეფერალები',
    'Plans': 'გეგმები',
    'AI Builders': 'AI ბილდერები',
    'Builders': 'ბილდერები',
    'AI Providers': 'AI პროვაიდერები',
    'Providers': 'პროვაიდერები',
    'CMS Sections': 'CMS სექციები',
    'Websites': 'საიტები',
    'Landing Page': 'ლენდინგ გვერდი',
    'Landing': 'ლენდინგი',
    'Plugins': 'პლაგინები',
    'Languages': 'ენები',
    'Cronjobs': 'Cron ამოცანები',
    'Operation Logs': 'ოპერაციის ლოგები',
    'Logs': 'ლოგები',
    'AI Bug Fixer': 'AI ბაგ ფიქსერი',
    'Bug Fixer': 'ბაგ ფიქსერი',
    'No components in config. Add entries in config/webu-builder-components.php.': 'კონფიგში კომპონენტები არ არის. დაამატე ჩანაწერები config/webu-builder-components.php-ში.',
    'Revert to this message': 'ამ მესიჯამდე დაბრუნება',
    'Helpful': 'სასარგებლო',
    'Not helpful': 'არასასარგებლო',
    'Copy message': 'შეტყობინების კოპირება',
    'More options': 'მეტი პარამეტრები',
    'Activity log': 'მოქმედებების ლოგი',
    'Could not copy': 'კოპირება ვერ მოხერხდა',
    'Preview': 'პრევიუ',
    'Project Workspace': 'პროექტის სივრცე',
    'Workspace': 'სამუშაო სივრცე',
    'Edits and creates your project': 'არედაქტირებს და ქმნის პროექტს',
    'Write to Webu': 'მიწერე Webu-ს',
    'Describe what you want to build or change': 'აღწერე რა გინდა შექმნა ან შეცვლა',
    "I've made the following changes:": 'შევასრულე შემდეგი ცვლილებები:',
    "I've updated the project. Summary:": 'პროექტი განახლდა. შეჯამება:',
    'Changes:': 'ცვლილებები:',
    "Done. I've applied your request.": 'მზადაა. შენი მოთხოვნა შესრულებულია.',
    'Analyzing...': 'ანალიზი...',
    'Understanding request...': 'მოთხოვნის გაგება...',
    'Planning changes...': 'ცვლილებების დაგეგმვა...',
    'Applying changes...': 'ცვლილებების გამოყენება...',
    'Back to Projects': 'პროექტებზე დაბრუნება',
    'Back': 'უკან',
    'Chats': 'ჩატები',
    'No chats yet': 'ჩატები ჯერ არ არის',
    'Today': 'დღეს',
    'Yesterday': 'გუშინ',
    'Previous 7 days': 'წინა 7 დღე',
    '7 days': '7 დღე',
    'Previous 30 days': 'წინა 30 დღე',
    '30 days': '30 დღე',
    'Older': 'ძველი',
    'Administration': 'ადმინისტრაცია',
    'Shipping': 'მიწოდება',
    'Values': 'მნიშვნელობები',
    'Activity': 'აქტივობა',
    'View Site': 'საიტის ნახვა',
    'CMS Manager': 'CMS მართვა',
    'CMS': 'CMS',
    'Blog': 'ბლოგი',
    'Booking': 'ჯავშანი',
    'Bookings': 'ჯავშნები',
    'Booking Overview': 'ჯავშნების მიმოხილვა',
    'Booking Calendar': 'ჯავშნების კალენდარი',
    'Booking Services': 'ჯავშნის სერვისები',
    'Booking Team': 'ჯავშნის გუნდი',
    'Booking Finance': 'ჯავშნის ფინანსები',
    'Booking Inbox': 'ჯავშნების ინბოქსი',
    'Booking Upcoming': 'მომავალი ჯავშნები',
    'Ecommerce Overview': 'ელკომერციის მიმოხილვა',
    'Ecommerce Orders': 'ელკომერციის შეკვეთები',
    'Current ecommerce order breakdown': 'ელკომერციის შეკვეთების მიმდინარე განაწილება',
    'Paid vs outstanding ecommerce revenue': 'გადახდილი და დარჩენილი ელკომერციის შემოსავალი',
    'Ecommerce workflow shortcuts': 'ელკომერციის სწრაფი მოქმედებები',
    'Store readiness and operational details': 'მაღაზიის მზადყოფნა და ოპერაციული დეტალები',
    'Top recent buyers': 'ბოლო აქტიური მყიდველები',
    'Content Overview': 'კონტენტის მიმოხილვა',
    'Quick Actions': 'სწრაფი მოქმედებები',
    'Publishing Coverage': 'გამოქვეყნების პროგრესი',
    'Blog Coverage': 'ბლოგის პროგრესი',
    'Media Library': 'მედია ბიბლიოთეკა',
    'Page Builder': 'გვერდის კონსტრუქტორი',
    'Main Page Builder': 'მთავარი გვერდის კონსტრუქტორი',
    'Elementor-style Page Builder': 'Elementor-ის მსგავსი გვერდის კონსტრუქტორი',
    'Page Metadata': 'გვერდის მეტამონაცემები',
    'Current Language': 'მიმდინარე ენა',
    'Search': 'ძებნა',
    'Cancel': 'გაუქმება',
    'Draft': 'დრაფტი',
    'Publish': 'გამოქვეყნება',
    'Product': 'პროდუქტი',
    'Price': 'ფასი',
    'Details': 'დეტალები',
    'Images': 'ფოტოები',
    'Gallery': 'გალერეა',
    'Stock': 'ნაშთი',
    'No image': 'ფოტო არ არის',
    'No images': 'ფოტოები არ არის',
    'No attributes yet': 'ატრიბუტები არ არის',
    'No variants yet': 'ვარიანტები არ არის',
    'Excerpt': 'მოკლე აღწერა',
    'Full Description': 'სრული აღწერა',
    'Short Description': 'მოკლე აღწერა',
    'Meta Description': 'მეტა აღწერა',
    'Meta Title': 'მეტა სათაური',
    'Cover Image': 'ქოვერ ფოტო',
    'Cover preview': 'ქოვერის პრევიუ',
    'Featured Image': 'მთავარი ფოტო',
    'Featured Image, gallery upload, reorder and delete': 'მთავარი ფოტო, გალერეის ატვირთვა, დალაგება და წაშლა',
    'Gallery Images': 'გალერეის ფოტოები',
    'Upload Gallery Images': 'გალერეის ფოტოების ატვირთვა',
    'Upload Featured Image': 'მთავარი ფოტო',
    'Blog Cover Image': 'ბლოგის ქოვერ ფოტო',
    'Write your blog post content...': 'ჩაწერე ბლოგის ტექსტი...',
    'Short post summary': 'ჩანაწერის მოკლე აღწერა',
    'Post title': 'ჩანაწერის სათაური',
    'Manage blog post content and publish status': 'ჩანაწერის ტექსტისა და სტატუსის მართვა',
    'Manage title, slug, and SEO fields': 'სათაურის, URL-ის და SEO ველების მართვა',
    'Manage pages like WordPress: add, edit, and delete from one list': 'გვერდების მართვა WordPress-ის სტილში: დამატება, რედაქტირება, წაშლა',
    'Create a new page and start editing with Builder or Text mode': 'შექმენი გვერდი და გააგრძელე ბილდერით ან ტექსტური რეჟიმით',
    'Text editor mode is ideal for fast content-only pages.': 'ტექსტური რეჟიმი კარგია სწრაფი კონტენტ-გვერდებისთვის.',
    'Drag components from the left panel to canvas. Select a section to edit content on the right.': 'ელემენტები გადაიტანე მარცხენა პანელიდან. სექციის არჩევის შემდეგ მარჯვნივ შეცვლი კონტენტს.',
    'Left panel controls widgets and settings, right panel shows full visual page.': 'მარცხენა პანელი მართავს ელემენტებს/პარამეტრებს, მარჯვენა მხარე აჩვენებს სრულ ვიზუალურ გვერდს.',
    'Collection schema management surface is planned in this slot. Use Entries/Page editor now for live content updates.': 'კოლექციების სქემის მართვა ამ ადგილზე დაემატება. ახლა გამოიყენე ჩანაწერები/გვერდის ედიტორი.',
    'Create/edit attributes and values from Add Product or Edit Product. This page shows database-driven summaries.': 'ატრიბუტები და მნიშვნელობები მართე პროდუქტის დამატება/რედაქტირებიდან. აქ ჩანს ბაზიდან წამოღებული შეჯამება.',
    'Catalog table with product edit/delete actions': 'კატალოგის ცხრილი პროდუქტის რედაქტირება/წაშლის მოქმედებებით',
    'Dynamic attributes and generated variants': 'დინამიკური ატრიბუტები და გენერირებული ვარიანტები',
    'Dynamic attributes are aggregated from product data in database': 'დინამიკური ატრიბუტები აგროვდება პროდუქტის მონაცემებიდან (ბაზა)',
    'Attribute values are aggregated from products and variants': 'ატრიბუტის მნიშვნელობები აგროვდება პროდუქტებიდან და ვარიანტებიდან',
    'Ecommerce core is active; this dedicated subsection UI is reserved here and linked from sidebar navigation.': 'ელკომერციის ბირთვი ჩართულია; ეს ქვეეკრანი მზადაა და მარცხენა მენიუდან იხსნება.',
    'Project-level settings surface for this subsection is mapped here.': 'ამ ქვე-მოდულის პროექტის დონეზე პარამეტრები აქ არის გამოტანილი.',
    'This section is prepared in the sidebar IA. Core module wiring is active; this screen is the next module-specific step.': 'ეს სექცია Sidebar სტრუქტურაში გამზადებულია. ძირითადი ინტეგრაცია ჩართულია; შემდეგი ნაბიჯია ამ ეკრანის დახურვა.',
    'Browse, upload, and edit media details': 'მედიის ნახვა, ატვირთვა და დეტალების რედაქტირება',
    'Select a page from the Pages tab to start editing': 'რედაქტირების დასაწყებად აირჩიე გვერდი გვერდების ტაბიდან',
    'Choose / Upload Image': 'სურათის არჩევა / ატვირთვა',
    'Choose / Upload Video': 'ვიდეოს არჩევა / ატვირთვა',
    'Edit alt text and description here (WordPress-like media details).': 'აქ ცვლი alt ტექსტს და აღწერას (WordPress-ის მსგავსი მედია დეტალები).',
    'Menu links are loaded automatically from Menu Builder.': 'მენიუს ლინკები ავტომატურად მოდის მენიუს კონსტრუქტორიდან.',
    'Choose a menu from the left panel': 'აირჩიე მენიუ მარცხენა პანელიდან',
    'Click + to add a page to selected menu': 'დააჭირე + რომ გვერდი დაამატო არჩეულ მენიუში',
    'Drag right = submenu, drag left = move level up': 'მარჯვნივ გაწევა = ქვემენიუ, მარცხნივ გაწევა = დონით ზემოთ',
    'Add Missing Template Sections': 'დაკარგული თემფლეით სექციების დამატება',
    'Missing template sections were added': 'დაკარგული თემფლეით სექციები დაემატა',
    'Apply Template Layout': 'თემფლეითის განლაგების გამოყენება',
    'Template layout applied to this page': 'თემფლეითის განლაგება გამოყენებულია ამ გვერდზე',
    'No editable fields for this section.': 'ამ სექციას რედაქტირებადი ველები არ აქვს.',
    'No editable options': 'რედაქტირებადი პარამეტრები არ არის',
    'Select section from structure': 'აირჩიე სექცია სტრუქტურიდან',
    'Select section and edit': 'აირჩიე სექცია და დაარედაქტირე',
    'Open Structure': 'სტრუქტურის გახსნა',
    'Collapse structure panel': 'სტრუქტურის პანელის ჩაკეცვა',
    'Open Builder': 'ბილდერის გახსნა',
    'Open Calendar': 'კალენდრის გახსნა',
    'Open Booking Inbox': 'ჯავშნების ინბოქსის გახსნა',
    'Open Orders': 'შეკვეთების გახსნა',
    'Open Products': 'პროდუქტების გახსნა',
    'Open Customers': 'კლიენტების გახსნა',
    'Open Media': 'მედიის გახსნა',
    'Open Media Manager': 'მედიის მენეჯერის გახსნა',
    'Open Preview': 'პრევიუს გახსნა',
    'Preview Website': 'საიტის პრევიუ',
    'Open Live Domain': 'პირდაპირი დომენის გახსნა',
    'Open Subdomain': 'ქვედომენის გახსნა',
    'Open preview or live website links below.': 'ქვემოთ გახსენი პრევიუს ან პირდაპირი საიტის ბმულები.',
    'Save Draft': 'დრაფტის შენახვა',
    'Website categories': 'ვებსაიტის კატეგორიები',
    'Business website': 'ბიზნეს ვებსაიტი',
    'Online store': 'ონლაინ მაღაზია',
    'Portfolio website': 'პორტფოლიოს ვებსაიტი',
    'Landing page': 'ლენდინგ გვერდი',
    'Blog website': 'ბლოგის ვებსაიტი',
    'Booking website': 'ჯავშნების ვებსაიტი',
    'Create a business website': 'შექმენი ბიზნეს ვებსაიტი',
    'Create an online store': 'შექმენი ონლაინ მაღაზია',
    'Create a landing page': 'შექმენი ლენდინგ გვერდი',
    'Create a blog website': 'შექმენი ბლოგის ვებსაიტი',
    'Create a booking website': 'შექმენი ჯავშნების ვებსაიტი',
    'Pick a website category to auto-fill your prompt.': 'აირჩიე ვებსაიტის კატეგორია და ტექსტი ავტომატურად ჩაიწერება ჩატში.',
    'Asset search': 'ასეტის ძებნა',
    'Search assets...': 'ასეტების ძებნა...',
    'Loading assets...': 'ასეტები იტვირთება...',
    'No assets found': 'ასეტები ვერ მოიძებნა',
    'Failed to load assets': 'ასეტების ჩატვირთვა ვერ მოხერხდა',
    'Start voice input': 'ხმის ჩაწერის დაწყება',
    'Stop voice input': 'ხმის ჩაწერის შეწყვეტა',
    'Browser does not support speech recognition.': 'ბრაუზერი არ უჭერს მხარს ხმის ამოცნობას.',
    'Microphone access was denied.': 'მიკროფონზე წვდომა უარყოფილია.',
    'Microphone permission is blocked': 'მიკროფონის ნებართვა დაბლოკილია',
    'Allow microphone access in your browser settings, then try again.': 'ბრაუზერის პარამეტრებში ჩართე მიკროფონის წვდომა და სცადე თავიდან.',
    'No speech was detected.': 'საუბარი ვერ დაფიქსირდა.',
    'Microphone is unavailable.': 'მიკროფონი მიუწვდომელია.',
    'Voice recognition failed. Please try again.': 'ხმის ამოცნობა ვერ მოხერხდა. სცადე თავიდან.',
    'Page published': 'გვერდი გამოქვეყნდა',
    'Draft revision saved': 'დრაფტის ვერსია შენახულია',
    'Global layout settings saved': 'გლობალური განლაგების პარამეტრები შენახულია',
    'Typography updated': 'ტიპოგრაფია განახლდა',
    'Site settings updated': 'საიტის პარამეტრები განახლდა',
    'Page metadata updated': 'გვერდის მეტამონაცემები განახლდა',
    'Page created successfully': 'გვერდი წარმატებით შეიქმნა',
    'Page deleted successfully': 'გვერდი წარმატებით წაიშალა',
    'Order updated successfully': 'შეკვეთა წარმატებით განახლდა',
    'Product created successfully': 'პროდუქტი წარმატებით შეიქმნა',
    'Product updated successfully': 'პროდუქტი წარმატებით განახლდა',
    'Product duplicated': 'პროდუქტი დაკოპირდა',
    'Product deleted': 'პროდუქტი წაიშალა',
    'Load demo products for preview': 'დემო პროდუქტების ჩატვირთვა პრევიუსთვის',
    'Demo products added. Your storefront preview will show sample products.': 'დემო პროდუქტები დაემატა. მაღაზიის პრევიუ აჩვენებს ნიმუშ პროდუქტებს.',
    'Failed to load demo products': 'დემო პროდუქტების ჩატვირთვა ვერ მოხერხდა',
    'Build a task management app': 'შექმენი დავალებების მართვის აპი',
    'Create a portfolio website': 'შექმენი პორტფოლიოს ვებსაიტი',
    'Design a landing page': 'დააპროექტე ლენდინგ გვერდი',
    'Make an e-commerce store': 'შექმენი ონლაინ მაღაზია',
    'Build me a modern portfolio website with dark mode...': 'შემიქმენი თანამედროვე პორტფოლიოს საიტი მუქი თემით...',
    'Create a task management app with drag and drop...': 'შექმენი დავალებების მართვის აპი drag-and-drop ფუნქციით...',
    'Design a landing page for my SaaS startup...': 'დააპროექტე ლენდინგ გვერდი ჩემი SaaS სტარტაპისთვის...',
    'Make an e-commerce store with cart functionality...': 'შემიქმენი ონლაინ მაღაზია კალათის ფუნქციით...',
    'Build a blog platform with markdown support...': 'შექმენი ბლოგის პლატფორმა Markdown მხარდაჭერით...',
    'Create a dashboard for tracking analytics...': 'შექმენი ანალიტიკის ტრეკინგის დეშბორდი...',
    'Design a booking system for appointments...': 'დააპროექტე შეხვედრების ჯავშნის სისტემა...',
    'Build a social media feed with infinite scroll...': 'შექმენი სოციალური ქსელის ფიდი უსასრულო სქროლით...',
    'Build your store': 'შექმენი შენი მაღაზია',
    'Generate my site': 'შექმენი ჩემი საიტი',
    'Generating…': 'იქმნება…',
    'Your store will be created with demo products. You can edit everything in the visual builder or return here to refine the design with chat.': 'მაღაზია შეიქმნება დემო პროდუქტებით. ყველაფრის რედაქტირება შეგიძლია ვიზუალურ ბილდერში ან დაბრუნდი აქ და დააზუსტე დიზაინი ჩატით.',
    'Site generated! Opening editor. You can open the live preview from the editor.': 'საიტი შეიქმნა. იხსნება ედიტორი. ცოცხალი პრევიუ ედიტორიდან გახსნა შეგიძლია.',
    'Failed to generate site': 'საიტის შექმნა ვერ მოხერხდა',
    'Thinking…': 'ფიქრობს…',
    'Type your answer…': 'ჩაწერე პასუხი…',
    'Answer a few questions and we\'ll generate your online store.': 'პასუხი რამდენიმე კითხვაზე და ჩვენ შევქმნით შენს ონლაინ მაღაზიას.',
    'Example: "I want an online store for fashion" or "I need a store that sells electronics". Type your answer below and the AI will ask a few short questions.': 'მაგალითად: „მინდა ონლაინ მაღაზია მოდისთვის“ ან „მჭირდება მაღაზია ელექტრონიკით“. ჩაწერე პასუხი ქვემოთ და AI დასვამს რამდენიმე მოკლე კითხვას.',
    'I have everything I need. Click "Generate my site" to create your store.': 'ყველაფერი მაქვს. დააჭირე „შექმენი ჩემი საიტი“ რომ მაღაზია შეიქმნას.',
    'Loading…': 'იტვირთება…',
    'Category created successfully': 'კატეგორია წარმატებით შეიქმნა',
    'Category updated successfully': 'კატეგორია წარმატებით განახლდა',
    'Category deleted': 'კატეგორია წაიშალა',
    'Booking created': 'ჯავშანი შეიქმნა',
    'Booking rescheduled': 'ჯავშანი გადაიდო',
    'Booking cancelled': 'ჯავშანი გაუქმდა',
    'Booking service created': 'ჯავშნის სერვისი შეიქმნა',
    'Booking service updated': 'ჯავშნის სერვისი განახლდა',
    'Booking service deleted': 'ჯავშნის სერვისი წაიშალა',
    'Booking staff/resource created': 'თანამშრომელი/რესურსი შეიქმნა',
    'Booking staff/resource updated': 'თანამშრომელი/რესურსი განახლდა',
    'Booking staff/resource deleted': 'თანამშრომელი/რესურსი წაიშალა',
    'Booking status updated': 'ჯავშნის სტატუსი განახლდა',
    'Media uploaded successfully': 'მედია წარმატებით აიტვირთა',
    'Media details updated': 'მედიის დეტალები განახლდა',
    'Media deleted': 'მედია წაიშალა',
    'Custom font uploaded successfully': 'კასტომ ფონტი წარმატებით აიტვირთა',
    'Custom font deleted': 'კასტომ ფონტი წაიშალა',
    'Featured image uploaded': 'მთავარი ფოტო აიტვირთა',
    'Gallery images uploaded': 'გალერეის ფოტოები აიტვირთა',
    'Webhook URL copied': 'Webhook URL დაკოპირდა',
    'Callback URL copied': 'Callback URL დაკოპირდა',
    'Copy URL': 'URL-ის კოპირება',
    'Copied': 'დაკოპირდა',
    'Current field': 'მიმდინარე ველი',
    'Current value': 'მიმდინარე მნიშვნელობა',
    'Updated': 'განახლდა',
    'No recent updates': 'ბოლო განახლებები არ არის',
    'Repeat': 'განმეორებითი',
    'VIP': 'VIP',
    'Unknown customer': 'უცნობი კლიენტი',
    'Type here': 'აქ დაწერე',
    'Read More': 'ვრცლად',
    'Shop Now': 'ახლავე ყიდვა',
    'Learn More': 'დაწვრილებით',
    'Subscribe and Get 25% Discount!': 'გამოიწერე და მიიღე 25% ფასდაკლება!',
    'Subscribe to the newsletter to receive updates about new products.': 'გამოიწერე სიახლეები და მიიღე განახლებები ახალ პროდუქტებზე.',
    'Welcome Popup': 'მისასალმებელი პოპაპი',
    'Popup Heading': 'პოპაპის სათაური',
    'Popup Description': 'პოპაპის აღწერა',
    'Popup Button': 'პოპაპის ღილაკი',
    'Popup is hidden on website until you turn it on.': 'პოპაპი საიტზე დამალულია სანამ არ ჩართავ.',
    'Here you change the global settings of the site.': 'აქედან ცვლი საიტის საერთო პარამეტრებს.',
    'აქედან ცვლი საიტის საერთო პარამეტრებს.': 'აქედან ცვლი საიტის საერთო პარამეტრებს.',
    'Dashboard': 'დაფა',
    'All Pages': 'ყველა გვერდი',
    'Blog Posts': 'ჩანაწერები',
    'Entries': 'ჩანაწერები',
    'Catalog': 'პროდუქტები',
    'Redirects': 'გადამისამართებები',
    'Collections': 'კოლექციები',
    'Fields': 'ველები',
    'Relationships': 'კავშირები',
    'All Products': 'ყველა პროდუქტი',
    'Add Product': 'პროდუქტის დამატება',
    'Categories': 'კატეგორიები',
    'Inventory': 'მარაგები',
    'Attributes': 'ატრიბუტები',
    'Attribute Values': 'ატრიბუტის მნიშვნელობები',
    'Variants': 'ვარიანტები',
    'All Orders': 'ყველა შეკვეთა',
    'Abandoned Carts': 'მიტოვებული კალათები',
    'Returns / Refunds': 'დაბრუნებები / დაბრუნებული თანხები',
    'Customers': 'კლიენტები',
    'Discounts': 'ფასდაკლებები',
    'Shipping & Delivery': 'მიწოდება',
    'Payments': 'გადახდები',
    'Media': 'მედია',
    'Design': 'დიზაინი',
    'Branding': 'ბრენდინგი',
    'Layout': 'განლაგება',
    'Menus': 'მენიუები',
    'Components Mapping': 'კომპონენტების მიბმა',
    'Theme Presets': 'თემის პრესეტები',
    'SEO': 'SEO',
    'Domain': 'დომენი',
    'General Settings': 'პარამეტრები',
    'Team & Roles': 'გუნდი და როლები',
    'Integrations': 'ინტეგრაციები',
    'Webhooks': 'ვებჰუკები',
    'Activity Log': 'აქტივობის ჟურნალი',
    'View Website': 'საიტის ნახვა',
    'Refine with Q&A': 'დაზუსტება Q&A-ით',
    'Projects': 'პროექტები',
    'General design settings': 'დიზაინის ზოგადი პარამეტრები',
    'Logo, colors, contact': 'ლოგო, ფერები, კონტაქტი',
    'Typography and layout': 'ტიპოგრაფია და განლაგება',
    'Header menu structure': 'ჰედერის მენიუს სტრუქტურა',
    'Template component bindings': 'თემფლეითის კომპონენტების მიბმა',
    'Saved presets and styles': 'შენახული პრესეტები და სტილები',
    'Page Details': 'გვერდის დეტალები',
    'Meta Data': 'მეტა მონაცემები',
    'Schedule': 'განრიგი',
    'Edit page title and link at the top. Meta data is shown below content.': 'ზემოთ შეცვალე გვერდის სათაური და ბმული. მეტა მონაცემები კონტენტის ქვემოთ არის.',
    'Search Customer': 'მომხმარებლის ძებნა',
    'Search by name or email': 'ძებნა სახელით ან ელფოსტით',
    'Registered user selected': 'რეგისტრირებული მომხმარებელი არჩეულია',
    'Search registered customer first, or enter details to auto-register on save': 'ჯერ მოძებნე რეგისტრირებული მომხმარებელი, ან შეიყვანე მონაცემები და შენახვისას ავტომატურად დარეგისტრირდება',
    'Search registered user or update customer information': 'მოძებნე რეგისტრირებული მომხმარებელი ან შეცვალე მომხმარებლის ინფორმაცია',
    'If user is not registered, booking save will create an account from name + email.': 'თუ მომხმარებელი რეგისტრირებული არ არის, ჯავშნის შენახვისას ანგარიში შეიქმნება სახელი + ელფოსტით.',
    'Searching customers...': 'კლიენტების ძებნა...',
    'No registered customer found': 'რეგისტრირებული კლიენტი ვერ მოიძებნა',
    'Click row to open details': 'დეტალების სანახავად დააჭირე რიგს',
    'Quick commands': 'სწრაფი ბრძანებები',
    'Run command': 'ბრძანების გაშვება',
    'Undo': 'გაუქმება',
    'Undo last AI command': 'ბოლო AI ბრძანების გაუქმება',
    'Undone': 'გაუქმებულია',
    'Command applied': 'ბრძანება გამოყენებულია',
    'Enter a command first': 'ჯერ შეიყვანე ბრძანება',
    'Failed to run AI command': 'AI ბრძანების გაშვება ვერ მოხერხდა',
    'Failed to interpret command': 'ბრძანების ინტერპრეტაცია ვერ მოხერხდა',
    'Apply content patch': 'კონტენტის პატჩის გამოყენება',
    'Select a page first': 'ჯერ აირჩიე გვერდი',
    'No changes were made.': 'ცვლილებები არ გაკეთდა.',
};

const KA_WORD_FALLBACK_TRANSLATIONS: Record<string, string> = {
    add: 'დამატება',
    new: 'ახალი',
    edit: 'რედაქტირება',
    update: 'განახლება',
    updated: 'განახლდა',
    delete: 'წაშლა',
    save: 'შენახვა',
    open: 'გახსნა',
    close: 'დახურვა',
    select: 'არჩევა',
    choose: 'არჩევა',
    upload: 'ატვირთვა',
    create: 'შექმნა',
    manage: 'მართვა',
    refresh: 'განახლება',
    reload: 'განახლება',
    search: 'ძებნა',
    filter: 'ფილტრი',
    filters: 'ფილტრები',
    apply: 'გამოყენება',
    cancel: 'გაუქმება',
    remove: 'წაშლა',
    clear: 'გასუფთავება',
    copy: 'კოპირება',
    copied: 'დაკოპირდა',
    load: 'ჩატვირთვა',
    loading: 'იტვირთება',
    failed: 'ვერ შესრულდა',
    no: 'არ არის',
    yes: 'კი',
    true: 'ჭეშმარიტი',
    false: 'მცდარი',
    all: 'ყველა',
    and: 'და',
    or: 'ან',
    with: 'ით',
    for: 'თვის',
    from: 'დან',
    to: 'მდე',
    by: 'მიხედვით',
    on: 'ჩართული',
    off: 'გამორთული',
    page: 'გვერდი',
    pages: 'გვერდები',
    post: 'ჩანაწერი',
    posts: 'ჩანაწერები',
    blog: 'ბლოგი',
    booking: 'ჯავშანი',
    schedule: 'განრიგი',
    content: 'კონტენტი',
    editor: 'ედიტორი',
    text: 'ტექსტი',
    title: 'სათაური',
    description: 'აღწერა',
    descriptions: 'აღწერები',
    image: 'სურათი',
    images: 'სურათები',
    video: 'ვიდეო',
    media: 'მედია',
    menu: 'მენიუ',
    menus: 'მენიუები',
    item: 'ელემენტი',
    items: 'ელემენტები',
    section: 'სექცია',
    sections: 'სექციები',
    widget: 'ვიჯეტი',
    widgets: 'ვიჯეტები',
    builder: 'ბილდერი',
    preview: 'პრევიუ',
    publish: 'გამოქვეყნება',
    published: 'გამოქვეყნებული',
    draft: 'დრაფტი',
    archived: 'არქივი',
    status: 'სტატუსი',
    statuses: 'სტატუსები',
    settings: 'პარამეტრები',
    global: 'გლობალური',
    local: 'ლოკალური',
    layout: 'განლაგება',
    template: 'თემფლეითი',
    templates: 'თემფლეითები',
    module: 'მოდული',
    modules: 'მოდულები',
    dashboard: 'დეშბორდი',
    overview: 'მიმოხილვა',
    quick: 'სწრაფი',
    actions: 'მოქმედებები',
    language: 'ენა',
    languages: 'ენები',
    current: 'მიმდინარე',
    repeat: 'განმეორებითი',
    default: 'ნაგულისხმევი',
    custom: 'კასტომ',
    font: 'ფონტი',
    fonts: 'ფონტები',
    typography: 'ტიპოგრაფია',
    logo: 'ლოგო',
    logoes: 'ლოგოები',
    header: 'ჰედერი',
    footer: 'ფუტერი',
    variant: 'ვარიანტი',
    variants: 'ვარიანტები',
    attribute: 'ატრიბუტი',
    attributes: 'ატრიბუტები',
    value: 'მნიშვნელობა',
    values: 'მნიშვნელობები',
    category: 'კატეგორია',
    categories: 'კატეგორიები',
    product: 'პროდუქტი',
    products: 'პროდუქტები',
    ecommerce: 'ელკომერცია',
    order: 'შეკვეთა',
    orders: 'შეკვეთები',
    payment: 'გადახდა',
    payments: 'გადახდები',
    provider: 'პროვაიდერი',
    providers: 'პროვაიდერები',
    shipping: 'მიწოდება',
    delivery: 'მიწოდება',
    shipment: 'გზავნილი',
    shipments: 'გზავნილები',
    inventory: 'ნაშთები',
    stock: 'სტოკი',
    quantity: 'რაოდენობა',
    price: 'ფასი',
    discount: 'ფასდაკლება',
    regular: 'ჩვეულებრივი',
    currency: 'ვალუტა',
    finance: 'ფინანსები',
    financial: 'ფინანსური',
    gross: 'მთლიანი',
    ledger: 'ლეიჯერი',
    accounting: 'ბუღალტერია',
    balance: 'ბალანსი',
    balances: 'ბალანსები',
    bookings: 'ჯავშნები',
    customer: 'კლიენტი',
    customers: 'კლიენტები',
    vip: 'vip',
    service: 'სერვისი',
    services: 'სერვისები',
    team: 'გუნდი',
    role: 'როლი',
    roles: 'როლები',
    staff: 'თანამშრომელი',
    resource: 'რესურსი',
    resources: 'რესურსები',
    calendar: 'კალენდარი',
    inbox: 'ინბოქსი',
    time: 'დრო',
    schedules: 'გრაფიკები',
    phone: 'ტელეფონი',
    email: 'ელფოსტა',
    contact: 'კონტაქტი',
    domain: 'დომენი',
    domains: 'დომენები',
    design: 'დიზაინი',
    branding: 'ბრენდინგი',
    component: 'კომპონენტი',
    components: 'კომპონენტები',
    mapping: 'მიბმა',
    preset: 'პრესეტი',
    presets: 'პრესეტები',
    saved: 'შენახული',
    style: 'სტილი',
    styles: 'სტილები',
    activity: 'აქტივობა',
    log: 'ჟურნალი',
    logs: 'ჟურნალები',
    project: 'პროექტი',
    projects: 'პროექტები',
    website: 'საიტი',
    view: 'ნახვა',
    redirect: 'გადამისამართება',
    redirects: 'გადამისამართებები',
    collection: 'კოლექცია',
    collections: 'კოლექციები',
    entry: 'ჩანაწერი',
    entries: 'ჩანაწერები',
    field: 'ველი',
    fields: 'ველები',
    relationship: 'კავშირი',
    relationships: 'კავშირები',
    integration: 'ინტეგრაცია',
    integrations: 'ინტეგრაციები',
    webhook: 'ვებჰუკი',
    webhooks: 'ვებჰუკები',
    return: 'დაბრუნება',
    returns: 'დაბრუნებები',
    refund: 'თანხის დაბრუნება',
    refunds: 'თანხის დაბრუნებები',
    method: 'მეთოდი',
    methods: 'მეთოდები',
    channel: 'არხი',
    channels: 'არხები',
    amount: 'თანხა',
    total: 'ჯამი',
    details: 'დეტალები',
    selected: 'არჩეული',
    required: 'სავალდებულო',
    optional: 'არასავალდებულო',
    invalid: 'არასწორი',
    latest: 'ბოლო',
    configure: 'კონფიგურაცია',
    configuration: 'კონფიგურაცია',
    summary: 'შეჯამება',
    general: 'ზოგადი',
    live: 'live',
    verify: 'დადასტურება',
    verification: 'დადასტურება',
    ssl: 'SSL',
    credentials: 'მონაცემები',
    registered: 'რეგისტრირებული',
    user: 'მომხმარებელი',
    users: 'მომხმარებლები',
    seo: 'SEO',
    meta: 'მეტა',
    url: 'URL',
    key: 'გასაღები',
    label: 'ლეიბლი',
    notes: 'შენიშვნები',
    note: 'შენიშვნა',
    internal: 'შიდა',
    available: 'ხელმისაწვდომი',
    active: 'აქტიური',
    inactive: 'არააქტიური',
    pending: 'მოლოდინში',
    confirmed: 'დადასტურებული',
    completed: 'დასრულებული',
    processing: 'დამუშავება',
    cancelled: 'გაუქმებული',
    delivered: 'მიწოდებული',
    in: 'ში',
    out: 'გარეთ',
    hand: 'ხელში',
    low: 'დაბალი',
    threshold: 'ზღვარი',
    source: 'წყარო',
    sources: 'წყაროები',
    type: 'ტიპი',
    mode: 'რეჟიმი',
    size: 'ზომა',
    color: 'ფერი',
    digital: 'ციფრული',
    gallery: 'გალერეა',
    featured: 'მთავარი',
};

function applyKaWordFallback(value: string): string {
    return value.replace(/\b[A-Za-z][A-Za-z0-9/-]*\b/g, (token) => {
        const normalized = token.toLowerCase();
        if (
            token.startsWith(':') ||
            token.includes('{{') ||
            token.includes('}}') ||
            normalized === 'url' ||
            normalized === 'seo' ||
            normalized === 'sku'
        ) {
            return token.toUpperCase() === token && token.length <= 4 ? token : token;
        }

        return KA_WORD_FALLBACK_TRANSLATIONS[normalized] ?? token;
    });
}

function translateMissingKa(key: string): string {
    if (KA_EXACT_FALLBACK_TRANSLATIONS[key]) {
        return KA_EXACT_FALLBACK_TRANSLATIONS[key];
    }

    const loadingMatch = key.match(/^Loading (.+)\.\.\.$/);
    if (loadingMatch) {
        return `${applyKaWordFallback(loadingMatch[1])} იტვირთება...`;
    }

    const failedMatch = key.match(/^Failed to (.+)$/);
    if (failedMatch) {
        return `${applyKaWordFallback(failedMatch[1])} ვერ მოხერხდა`;
    }

    const noYetMatch = key.match(/^No (.+?) yet\.?$/);
    if (noYetMatch) {
        return `ჯერ ${applyKaWordFallback(noYetMatch[1])} არ არის`;
    }

    const noMatch = key.match(/^No (.+)$/);
    if (noMatch) {
        return `${applyKaWordFallback(noMatch[1])} არ არის`;
    }

    const createMatch = key.match(/^Create (.+)$/);
    if (createMatch) {
        return `${applyKaWordFallback(createMatch[1])} შექმნა`;
    }

    const editMatch = key.match(/^Edit (.+)$/);
    if (editMatch) {
        return `${applyKaWordFallback(editMatch[1])} რედაქტირება`;
    }

    const updateMatch = key.match(/^Update (.+)$/);
    if (updateMatch) {
        return `${applyKaWordFallback(updateMatch[1])} განახლება`;
    }

    const deleteMatch = key.match(/^Delete (.+)$/);
    if (deleteMatch) {
        return `${applyKaWordFallback(deleteMatch[1])} წაშლა`;
    }

    const refreshMatch = key.match(/^Refresh (.+)$/);
    if (refreshMatch) {
        return `${applyKaWordFallback(refreshMatch[1])} განახლება`;
    }

    const openMatch = key.match(/^Open (.+)$/);
    if (openMatch) {
        return `${applyKaWordFallback(openMatch[1])} გახსნა`;
    }

    const selectMatch = key.match(/^Select (.+)$/);
    if (selectMatch) {
        return `${applyKaWordFallback(selectMatch[1])} არჩევა`;
    }

    const searchMatch = key.match(/^Search (.+)$/);
    if (searchMatch) {
        return `${applyKaWordFallback(searchMatch[1])} ძებნა`;
    }

    const saveMatch = key.match(/^Save (.+)$/);
    if (saveMatch) {
        return `${applyKaWordFallback(saveMatch[1])} შენახვა`;
    }

    const uploadMatch = key.match(/^Upload (.+)$/);
    if (uploadMatch) {
        return `${applyKaWordFallback(uploadMatch[1])} ატვირთვა`;
    }

    return applyKaWordFallback(key);
}

export function LanguageProvider({ children }: LanguageProviderProps) {
    const props = usePage().props as {
        locale?: LocaleData;
        translations?: Record<string, string>;
    };

    const locale = props.locale?.current ?? 'ka';
    const isRtl = props.locale?.isRtl ?? false;
    const availableLanguages = useMemo(
        () => props.locale?.available ?? [],
        [props.locale?.available]
    );
    const translations = useMemo(
        () => props.translations ?? {},
        [props.translations]
    );

    // Apply RTL and lang to document
    useEffect(() => {
        document.documentElement.dir = isRtl ? 'rtl' : 'ltr';
        document.documentElement.lang = locale;
        localStorage.setItem(STORAGE_KEY, locale);
        localStorage.setItem(RTL_STORAGE_KEY, String(isRtl));
    }, [isRtl, locale]);

    const setLocale = useCallback((newLocale: string) => {
        // Store locale and RTL status in localStorage
        localStorage.setItem(STORAGE_KEY, newLocale);
        const newLanguage = availableLanguages.find(
            (lang) => lang.code === newLocale
        );
        localStorage.setItem(RTL_STORAGE_KEY, String(newLanguage?.is_rtl ?? false));

        router.post(
            '/locale',
            { locale: newLocale },
            {
                preserveState: false,
                preserveScroll: true,
            }
        );
    }, [availableLanguages]);

    const t = useMemo(() => {
        return (
            key: string,
            replacements?: Record<string, string | number>
        ): string => {
            let translation = translations?.[key];
            if (locale === 'ka' && KA_EXACT_FALLBACK_TRANSLATIONS[key]) {
                translation = KA_EXACT_FALLBACK_TRANSLATIONS[key];
            } else if (translation === undefined) {
                translation = locale === 'ka' ? translateMissingKa(key) : key;
            }

            if (replacements) {
                Object.entries(replacements).forEach(([k, v]) => {
                    translation = translation.replace(`:${k}`, String(v));
                });
            }

            return translation;
        };
    }, [locale, translations]);

    const value = useMemo(
        () => ({
            locale,
            isRtl,
            availableLanguages,
            setLocale,
            t,
        }),
        [locale, isRtl, availableLanguages, setLocale, t]
    );

    return (
        <LanguageContext.Provider value={value}>
            {children}
        </LanguageContext.Provider>
    );
}

/** Fallback context when LanguageProvider is not yet mounted (e.g. initial Inertia load). Avoids "useLanguage must be used within a LanguageProvider" crash. */
function getFallbackLanguageContext(): LanguageContextType {
    const locale =
        (typeof window !== 'undefined' && localStorage.getItem(STORAGE_KEY)) || 'ka';
    const isRtl =
        typeof window !== 'undefined' &&
        localStorage.getItem(RTL_STORAGE_KEY) === 'true';
    return {
        locale,
        isRtl,
        availableLanguages: [],
        setLocale: () => {},
        t: (key: string, replacements?: Record<string, string | number>): string => {
            let translation = locale === 'ka' ? translateMissingKa(key) : key;
            if (replacements) {
                Object.entries(replacements).forEach(([k, v]) => {
                    translation = translation.replace(`:${k}`, String(v));
                });
            }
            return translation;
        },
    };
}

export function useLanguage(): LanguageContextType {
    const context = useContext(LanguageContext);
    if (context === undefined) {
        return getFallbackLanguageContext();
    }
    return context;
}

// Convenience hook for translations only
export function useTranslation() {
    const { t, locale, isRtl } = useLanguage();
    return { t, locale, isRtl };
}
