<?php

namespace Database\Seeders;

use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\ThemePreset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        PortfolioSetting::query()->firstOrCreate(['id' => 1], [
            'name' => 'Hamid Reza Bayat',
            'professional_title' => 'Software Developer',
            'github_url' => 'https://github.com/hamidreza-wq',
            'site_title' => 'Hamid Reza Bayat — Software Developer',
            'site_description' => 'A small universe of useful software, carefully built.',
            'color_background' => '#070811',
            'color_foreground' => '#f3f1f8',
            'color_primary' => '#ad91ff',
            'color_accent' => '#c4b1ff',
            'color_cyan' => '#92d8ee',
            'font_body' => 'Inter',
            'font_heading' => 'Inter',
            'max_width' => 1180,
            'section_spacing' => 140,
            'border_radius' => 12,
            'footer_text' => 'Made with curiosity, somewhere on Earth.',
        ]);

        $sections = [
            ['home', 'Hero', 'A universe of useful ideas.', 'DIGITAL CRAFT', "I'm Hamid Reza Bayat — I build thoughtful software for real-world problems, from web platforms to tools that work right on your desktop.", 0],
            ['about', 'About', 'A little context. A lot of curiosity.', 'THE BUILDER', 'The best software makes complicated things feel clear — and gives people more room to do what matters. I work across product ideas, interfaces, and the systems behind them.', 1],
            ['work', 'Selected work', "Things I've put into orbit.", 'SELECTED WORK', 'Real projects. Real repositories. No borrowed case studies.', 2],
            ['craft', 'Skills', 'Built across the stack.', 'THE TOOLKIT', 'Technologies reflected in the projects above — not a list of claims.', 3],
            ['journey', 'Approach', 'From a useful question to a thoughtful tool.', 'THE APPROACH', 'Understand the real job. Make the system clear. Refine the details.', 4],
            ['contact', 'Contact', 'Have a good problem to solve?', 'NEXT CONNECTION', "I'm always interested in thoughtful ideas, useful software, and good collaborators.", 5],
        ];

        foreach ($sections as [$key, $label, $title, $subtitle, $body, $order]) {
            PortfolioSection::query()->updateOrCreate(['key' => $key], [
                'label' => $label,
                'title' => $title,
                'subtitle' => $subtitle,
                'body' => $body,
                'is_visible' => true,
                'sort_order' => $order,
                'background_effect' => 'cosmic',
                'spacing_top' => 100,
                'spacing_bottom' => 100,
            ]);
        }

        $projects = [
            ['OnePrayer', 'mobile', 'MOBILE + WEB / EARLY BUILD', 'Bible', 'An early devotional product with a React web shell, Laravel API, and an Expo mobile app scaffold.', 'The repository documents a React and TypeScript web experience consuming sample API content, plus a Laravel REST API and a scaffolded Expo client. Native device testing and mobile product screens are not complete.', ['Laravel', 'React', 'TypeScript', 'Expo', 'React Native', 'MySQL'], ['English and Persian sample devotional journey on web', 'Laravel API with MySQL/MariaDB architecture', 'Mobile client scaffolded; native product screens remain in progress'], 'prayer', 'DEVOTIONAL / EARLY BUILD', 0],
            ['Office Converters', 'tools', 'DESKTOP & WEB', 'officeConverters', 'A local document converter with an offline Windows desktop installer, alongside a separately deployable Laravel web application.', 'The Windows app works locally without a cloud conversion service. The project combines strict tabular adapters with PDF/OCR, Office, image, and ZIP conversion engines. Its README documents 117 implemented conversion pairs, explicit fidelity warnings, and remaining release gates.', ['PHP', 'Laravel', 'Python', 'Electron', 'LibreOffice', 'Tesseract'], ['Offline Windows desktop installer', 'PDF, OCR, Office, image, and ZIP conversion engines', 'Local document processing; no external conversion service'], 'converter', 'LOCAL-FIRST / DOCUMENT TOOLS', 1],
            ['Home Ledger', 'web', 'WEB APPLICATION', 'myAccountingHome', 'A family-finance application for recording GBP income, expenses, transfers, and budgets.', 'Home Ledger is a Laravel family-finance starter with separate family workspaces, private and shared records, transaction history, monthly budgets, and reporting. Its documented stack includes Laravel, Blade, Tailwind CSS, Vite, and MySQL or MariaDB.', ['PHP', 'Laravel', 'Blade', 'Tailwind CSS', 'MySQL', 'Vite'], ['Income, expenses, transfers, and account balances', 'Private and shared family records', 'Budgets, reports, and preview-first statement imports'], 'ledger', 'FAMILY FINANCE / HOME LEDGER', 2],
            ['Interpreters Hub', 'web', 'WEB PLATFORM', 'interpurting-laravel', 'A language platform with a public React site, Laravel content dashboard, and community discussion space.', 'The repository describes a React public site served by Laravel, with SQL-backed website content and glossary records. It also includes a language-desk forum where guests can browse and members can participate in discussions.', ['PHP', 'Laravel', 'React', 'MySQL'], ['Public language platform and editable content', 'SQL-backed glossary records', 'Community discussions with member and admin controls'], 'language', 'LANGUAGE / COMMUNITY', 3],
            ['BetweenWords', 'web', 'BILINGUAL WEB PLATFORM', 'meisaPortfolio', 'A Farsi–English community interpreting website with a content-management dashboard.', 'BetweenWords includes English and Persian public pages, right-to-left localization, a bilingual content dashboard, media management, and an enquiry workflow. Its README documents a prerendered React frontend served by Laravel 13, with MySQL as its database.', ['PHP', 'Laravel', 'React', 'MySQL'], ['English and Persian content with RTL support', 'Editable services, articles, and portfolio content', 'Enquiry management with validation and spam controls'], 'betweenwords', 'FARSI ↔ ENGLISH / ACCESS', 4],
        ];

        foreach ($projects as [$name, $category, $categoryLabel, $repo, $description, $details, $technologies, $features, $visual, $visualLabel, $order]) {
            Project::query()->updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'category' => $category,
                'category_label' => $categoryLabel,
                'repository_url' => 'https://github.com/hamidreza-wq/'.$repo,
                'description' => $description,
                'details' => $details,
                'technologies' => $technologies,
                'features' => $features,
                'visual' => $visual,
                'visual_label' => $visualLabel,
                'status' => $category === 'mobile' ? 'early build' : 'active',
                'is_featured' => $order < 3,
                'is_published' => true,
                'sort_order' => $order,
            ]);
        }

        $groups = [
            ['Web & interfaces', ['React', 'JavaScript', 'Blade', 'Tailwind CSS', 'Vite']],
            ['Backend & data', ['PHP', 'Laravel', 'MySQL', 'MariaDB', 'SQLite']],
            ['Desktop & document tools', ['Python', 'Electron', 'LibreOffice', 'Tesseract OCR']],
            ['Mobile & shared systems', ['TypeScript', 'Expo', 'React Native']],
        ];

        foreach ($groups as $order => [$title, $skills]) {
            $category = SkillCategory::query()->updateOrCreate(['title' => $title], [
                'sort_order' => $order,
                'is_visible' => true,
            ]);

            foreach ($skills as $skillOrder => $skill) {
                Skill::query()->updateOrCreate([
                    'skill_category_id' => $category->id,
                    'name' => $skill,
                ], [
                    'sort_order' => $skillOrder,
                    'is_visible' => true,
                ]);
            }
        }

        ThemePreset::query()->firstOrCreate(['name' => 'Cosmic default'], [
            'color_background' => '#070811',
            'color_foreground' => '#f3f1f8',
            'color_primary' => '#ad91ff',
            'color_accent' => '#c4b1ff',
            'color_cyan' => '#92d8ee',
            'is_active' => true,
        ]);
    }
}
