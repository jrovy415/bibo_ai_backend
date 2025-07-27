<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = ['create', 'view', 'update', 'delete'];
        $modules = $this->getAllModels();

        $exclude = [
            'App\Models\RolePermission',
        ];

        foreach ($modules as $model => $module) {
            if (!in_array($model, $exclude)) {
                foreach ($actions as $action) {
                    $name = "{$action} {$module}";
                    $slug = Str::slug($name);

                    if (!Permission::where('model', $model)->where('slug', $slug)->exists()) {
                        Permission::create([
                            'model' => $model,
                            'name' => $name,
                            'slug' => $slug
                        ]);
                    }
                }
            }
        }
    }

    private function getAllModels(): array
    {
        $modelPath = app_path('Models');
        $namespace = 'App\\Models\\';
        $files = scandir($modelPath);
        $models = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $className = pathinfo($file, PATHINFO_FILENAME);
            $fullClassName = $namespace . $className;

            if (class_exists($fullClassName)) {
                $models[$fullClassName] = Str::plural(Str::snake($className));
            }
        }

        return $models;
    }
}
