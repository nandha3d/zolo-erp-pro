<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectManagementController extends Controller
{
    public function projects()
    {
        $projects = DB::table('projects')
            ->leftJoin('project_categories', 'projects.category_id', '=', 'project_categories.id')
            ->leftJoin('customers', 'projects.client_id', '=', 'customers.id')
            ->select('projects.*', 'project_categories.name as category_name', 'customers.name as client_name')
            ->latest()
            ->paginate(15);

        $categories = DB::table('project_categories')->get();
        $clients = Customer::where('is_active', true)->get();

        $stats = [
            'total' => DB::table('projects')->count(),
            'in_progress' => DB::table('projects')->where('status', 'In Progress')->count(),
            'completed' => DB::table('projects')->where('status', 'Completed')->count(),
            'total_budget' => DB::table('projects')->sum('budget'),
        ];

        return view('backend.project_management.projects', compact('projects', 'categories', 'clients', 'stats'));
    }

    public function storeProject(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'category_id' => 'required',
            'start_date' => 'required|date',
        ]);

        DB::table('projects')->insert([
            'title' => $request->title,
            'category_id' => $request->category_id,
            'client_id' => $request->client_id,
            'start_date' => $request->start_date,
            'deadline' => $request->deadline,
            'budget' => (float)($request->budget ?? 0),
            'status' => $request->status ?? 'Not Started',
            'progress_percent' => (int)($request->progress_percent ?? 0),
            'description' => $request->description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Project created successfully.');
    }

    public function categories()
    {
        $categories = DB::table('project_categories')
            ->leftJoin('projects', 'project_categories.id', '=', 'projects.category_id')
            ->select('project_categories.*', DB::raw('COUNT(projects.id) as projects_count'))
            ->groupBy('project_categories.id', 'project_categories.name', 'project_categories.description', 'project_categories.created_at', 'project_categories.updated_at')
            ->get();

        return view('backend.project_management.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string']);

        DB::table('project_categories')->insert([
            'name' => $request->name,
            'description' => $request->description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Project category added successfully.');
    }

    public function tasks()
    {
        $tasks = DB::table('project_tasks')
            ->join('projects', 'project_tasks.project_id', '=', 'projects.id')
            ->leftJoin('users', 'project_tasks.assigned_to', '=', 'users.id')
            ->select('project_tasks.*', 'projects.title as project_title', 'users.name as assignee_name')
            ->latest()
            ->paginate(20);

        $projects = DB::table('projects')->select('id', 'title')->get();
        $users = User::where('is_active', true)->select('id', 'name')->get();

        return view('backend.project_management.tasks', compact('tasks', 'projects', 'users'));
    }

    public function storeTask(Request $request)
    {
        $request->validate([
            'project_id' => 'required',
            'title' => 'required|string',
        ]);

        DB::table('project_tasks')->insert([
            'project_id' => $request->project_id,
            'title' => $request->title,
            'assigned_to' => $request->assigned_to,
            'due_date' => $request->due_date,
            'priority' => $request->priority ?? 'Medium',
            'status' => $request->status ?? 'Todo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Task created successfully.');
    }

    public function updateTaskStatus(Request $request, $id)
    {
        $status = $request->status;
        DB::table('project_tasks')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
