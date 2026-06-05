<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@sphereos.app'],
            ['name' => 'Demo Founder', 'password' => Hash::make('password')],
        );

        $clients = [
            ['name' => 'PT Maju Bersama', 'company' => 'PT Maju Bersama', 'email' => 'info@majubersama.id', 'phone' => '+62 812 0001 0001', 'status' => 'client'],
            ['name' => 'CV Teknologi Digital', 'company' => 'CV Teknologi Digital', 'email' => 'hello@tekdig.id', 'status' => 'prospect'],
            ['name' => 'Budi Santoso', 'company' => 'Freelance', 'email' => 'budi@email.com', 'status' => 'lead', 'next_action_date' => now()->addDays(3)->toDateString(), 'next_action_note' => 'Follow up after demo call'],
        ];

        foreach ($clients as $clientData) {
            $client = Client::create(array_merge($clientData, ['user_id' => $user->id]));

            Contact::create([
                'user_id'   => $user->id,
                'client_id' => $client->id,
                'name'      => 'Contact for ' . $client->name,
                'email'     => 'contact@example.com',
                'role'      => 'Manager',
            ]);

            if ($client->status === 'client') {
                $project = Project::create([
                    'user_id'     => $user->id,
                    'client_id'   => $client->id,
                    'name'        => 'Website Redesign',
                    'description' => 'Full redesign of company website.',
                    'status'      => 'active',
                    'due_date'    => now()->addDays(30)->toDateString(),
                ]);

                $tasks = [
                    ['title' => 'Kickoff meeting', 'status' => 'done', 'priority' => 'high'],
                    ['title' => 'Wireframe design', 'status' => 'in_progress', 'priority' => 'high', 'due_date' => now()->addDays(5)->toDateString()],
                    ['title' => 'Frontend development', 'status' => 'todo', 'priority' => 'medium', 'due_date' => now()->addDays(20)->toDateString()],
                    ['title' => 'QA testing', 'status' => 'todo', 'priority' => 'low', 'due_date' => now()->addDays(28)->toDateString()],
                ];

                foreach ($tasks as $taskData) {
                    Task::create(array_merge($taskData, ['user_id' => $user->id, 'project_id' => $project->id]));
                }
            }
        }
    }
}
