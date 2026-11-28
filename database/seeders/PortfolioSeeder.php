<?php

namespace Database\Seeders;

use App\Models\Portfolio;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'title' => 'ICT Solution',
                'category' => 'Web Development',
                'description' => 'Full-stack enterprise web platform and digital service portal built with React and Laravel.',
                'image_url' => '/img/software/ict-solut.png',
                'tech_stack' => ['React', 'Laravel', 'REST API'],
                'icon' => 'globe',
                'live_url' => 'https://ict-solution-2.vercel.app',
                'is_published' => true,
                'sort_order' => 0,
            ],
            [
                'title' => 'ICT Center',
                'category' => 'Web Development',
                'description' => 'Institutional web platform and backend service API for managing programs and digital content.',
                'image_url' => '/img/software/ict-center.png',
                'tech_stack' => ['Laravel', 'REST API'],
                'icon' => 'globe',
                'live_url' => null,
                'is_published' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($items as $item) {
            Portfolio::updateOrCreate(
                ['title' => $item['title']],
                $item
            );
        }
    }
}
