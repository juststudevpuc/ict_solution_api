<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CareerController extends Controller
{
    // Get all open careers (For the public React page)
    public function index()
    {
        // Only show 'open' jobs to the public
        $careers = Career::where('status', 'open')->latest()->paginate(10);

        return response()->json([
            "data" => $careers,
            "message" => "Get open careers success"
        ]);
    }

    // Get ALL careers including drafts/closed (For the Admin Dashboard)
    public function adminIndex()
    {
        $careers = Career::latest()->paginate(10);

        return response()->json([
            "data" => $careers,
            "message" => "Get all careers success"
        ]);
    }

    // Create a new job posting
    public function store(Request $request)
    {
        $validate = $request->validate([
            "title" => "required|string|max:255",
            "department" => "nullable|string",
            "job_type" => "required|string",
            "location" => "nullable|string",
            "job_level" => "nullable|string",
            "vacancies" => "integer|min:1",
            "salary_range" => "nullable|string",
            "job_description" => "required|string",
            "job_requirement" => "nullable|array",
            "job_responsibility" => "nullable|array",
            "closing_date" => "nullable|date",
            "status" => "required|string|in:open,closed,draft"
        ]);

        // Automatically create a URL slug (e.g., "senior-web-developer")
        $validate['slug'] = Str::slug($validate['title']) . '-' . uniqid();

        $career = Career::create($validate);

        return response()->json([
            "data" => $career,
            "message" => "Career posting created successfully"
        ]);
    }

    // View a single job by its ID or Slug
    public function show($id)
    {
        $career = Career::where('_id', $id)->orWhere('slug', $id)->first();

        if (!$career) {
            return response()->json(["data" => null, "message" => "Career not found"], 404);
        }

        return response()->json([
            "data" => $career,
            "message" => "Get career details success"
        ]);
    }

    // Update a job posting
    public function update(Request $request, $id)
    {
        $career = Career::find($id);

        if (!$career) {
            return response()->json(["data" => null, "message" => "Career not found"], 404);
        }

        $validate = $request->validate([
            "title" => "sometimes|required|string|max:255",
            "department" => "nullable|string",
            "job_type" => "sometimes|required|string",
            "location" => "nullable|string",
            "job_level" => "nullable|string",
            "vacancies" => "integer|min:1",
            "salary_range" => "nullable|string",
            "job_description" => "sometimes|required|string",
            "job_requirement" => "nullable|array",
            "job_responsibility" => "nullable|array",
            "closing_date" => "nullable|date",
            "status" => "sometimes|required|string|in:open,closed,draft"
        ]);

        if (isset($validate['title'])) {
            $validate['slug'] = Str::slug($validate['title']) . '-' . uniqid();
        }

        $career->update($validate);

        return response()->json([
            "data" => $career,
            "message" => "Career posting updated successfully"
        ]);
    }

    // Soft delete a job posting
    public function destroy($id)
    {
        $career = Career::find($id);

        if (!$career) {
            return response()->json(["data" => null, "message" => "Career not found"], 404);
        }

        $career->delete(); // This triggers a soft delete because of the model trait

        return response()->json([
            "data" => null,
            "message" => "Career posting archived successfully"
        ]);
    }
}
