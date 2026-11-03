export const profile = {
  name: "Hamid Reza Bayat",
  title: "Software Developer",
  github: "https://github.com/hamidreza-wq",
  email: "",
  linkedin: "",
};

export const projects = [
  {
    name: "OnePrayer",
    category: "mobile",
    categoryLabel: "MOBILE + WEB / EARLY BUILD",
    repo: "Bible",
    description:
      "An early devotional product with a React web shell, Laravel API, and an Expo mobile app scaffold.",
    detail:
      "The repository documents a React and TypeScript web experience consuming sample API content, plus a Laravel REST API and a scaffolded Expo client. The project is explicitly still in progress: its architecture notes say native device testing and mobile product screens are not complete.",
    technologies: ["Laravel", "React", "TypeScript", "Expo", "React Native", "MySQL"],
    features: [
      "English and Persian sample devotional journey on web",
      "Laravel API with MySQL/MariaDB architecture",
      "Mobile client scaffolded; native product screens remain in progress",
    ],
    visual: "prayer",
    visualLabel: "DEVOTIONAL / EARLY BUILD",
  },
  {
    name: "Office Converters",
    category: "tools",
    categoryLabel: "DESKTOP & WEB",
    repo: "officeConverters",
    description:
      "A local document converter with an offline Windows desktop installer, alongside a separately deployable Laravel web application.",
    detail:
      "The Windows app works locally without a cloud conversion service. The project combines strict tabular adapters with PDF/OCR, Office, image, and ZIP conversion engines. Its README documents 117 implemented conversion pairs, explicit fidelity warnings, and the release gates that remain.",
    technologies: ["PHP", "Laravel", "Python", "Electron", "LibreOffice", "Tesseract"],
    features: [
      "Offline Windows desktop installer",
      "PDF, OCR, Office, image, and ZIP conversion engines",
      "Local document processing; no external conversion service",
    ],
    visual: "converter",
    visualLabel: "LOCAL-FIRST / DOCUMENT TOOLS",
  },
  {
    name: "Home Ledger",
    category: "web",
    categoryLabel: "WEB APPLICATION",
    repo: "myAccountingHome",
    description:
      "A family-finance application for recording GBP income, expenses, transfers, and budgets.",
    detail:
      "Home Ledger is a Laravel family-finance starter with separate family workspaces, private and shared records, transaction history, monthly budgets, and reporting. Its documented stack includes Laravel, Blade, Tailwind CSS, Vite, and MySQL or MariaDB.",
    technologies: ["PHP", "Laravel", "Blade", "Tailwind CSS", "MySQL", "Vite"],
    features: [
      "Income, expenses, transfers, and account balances",
      "Private and shared family records",
      "Budgets, reports, and preview-first statement imports",
    ],
    visual: "ledger",
    visualLabel: "FAMILY FINANCE / HOME LEDGER",
  },
  {
    name: "Interpreters Hub",
    category: "web",
    categoryLabel: "WEB PLATFORM",
    repo: "interpurting-laravel",
    description:
      "A language platform with a public React site, Laravel content dashboard, and community discussion space.",
    detail:
      "The repository describes a React public site served by Laravel, with SQL-backed website content and glossary records. It also includes a language-desk forum where guests can browse and members can participate in discussions.",
    technologies: ["PHP", "Laravel", "React", "MySQL"],
    features: [
      "Public language platform and editable content",
      "SQL-backed glossary records",
      "Community discussions with member and admin controls",
    ],
    visual: "language",
    visualLabel: "LANGUAGE / COMMUNITY",
  },
  {
    name: "BetweenWords",
    category: "web",
    categoryLabel: "BILINGUAL WEB PLATFORM",
    repo: "meisaPortfolio",
    description:
      "A Farsi–English community interpreting website with a content-management dashboard.",
    detail:
      "BetweenWords includes English and Persian public pages, right-to-left localization, a bilingual content dashboard, media management, and an enquiry workflow. Its README documents a prerendered React frontend served by Laravel 13, with MySQL as its database.",
    technologies: ["PHP", "Laravel", "React", "MySQL"],
    features: [
      "English and Persian content with RTL support",
      "Editable services, articles, and portfolio content",
      "Enquiry management with validation and spam controls",
    ],
    visual: "betweenwords",
    visualLabel: "FARSI ↔ ENGLISH / ACCESS",
  },
];

export const craft = [
  {
    title: "Web & interfaces",
    items: ["React", "JavaScript", "Blade", "Tailwind CSS", "Vite"],
  },
  {
    title: "Backend & data",
    items: ["PHP", "Laravel", "MySQL", "MariaDB"],
  },
  {
    title: "Desktop & document tools",
    items: ["Python", "Electron", "LibreOffice", "Tesseract OCR"],
  },
  {
    title: "Mobile & shared systems",
    items: ["TypeScript", "Expo", "React Native"],
  },
];
