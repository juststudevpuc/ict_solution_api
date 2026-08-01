<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    // Get all applications (Admin Dashboard)
    public function index(Request $request)
    {
        // Eager load the career details so the admin knows which job they applied for
        $applications = JobApplication::with('career')->latest()->paginate(15);

        return response()->json([
            "data" => $applications,
            "message" => "Get applications success"
        ]);
    }

    // Submit a new application (Public React Form)

    public function store(Request $request)
    {
        $validate = $request->validate([
            "career_id" => "required|string|exists:careers,_id",
            "first_name" => "required|string|max:100",
            "last_name" => "required|string|max:100",
            "email" => "required|email",
            "phone" => "required|string|max:20",
            "experience_years" => "nullable|integer",
            "expected_salary" => "nullable|string",
            "cover_letter" => "nullable|string",
            "portfolio_url" => "nullable|url",
            "cv_file" => "required|file|mimes:pdf,doc,docx|max:5120",
        ]);

        if ($request->hasFile("cv_file")) {
            $file = $request->file("cv_file");

            // Upload to Cloudinary
            $upload = Cloudinary::uploadApi()->upload(
                $file->getRealPath(),
                [
                    "folder" => "ict_solu_cvs", // Keeps your CVs organized in their own folder
                    "resource_type" => "auto"   // 🔥 CRITICAL: Required for PDFs and Docs
                ]
            );

            // Save the clean Cloudinary URL and ID for your React app
            $validate["cv_url"] = $upload["secure_url"];
            $validate["cv_public_id"] = $upload["public_id"];
        }

        $application = JobApplication::create($validate);

        return response()->json([
            "data" => $application,
            "message" => "Application submitted successfully"
        ], 201);
    }
    // View a specific candidate's application
    public function show($id)
    {
        $application = JobApplication::with('career')->find($id);

        if (!$application) {
            return response()->json(["data" => null, "message" => "Application not found"], 404);
        }

        return response()->json([
            "data" => $application,
            "message" => "Get application details success"
        ]);
    }

    // Update application status or add admin notes
    public function update(Request $request, $id)
    {
        $application = JobApplication::find($id);

        if (!$application) {
            return response()->json(["data" => null, "message" => "Application not found"], 404);
        }

        $validate = $request->validate([
            "status" => "sometimes|required|string|in:pending,reviewing,interview,rejected,hired",
            "admin_notes" => "nullable|string"
        ]);

        $application->update($validate);

        return response()->json([
            "data" => $application,
            "message" => "Application status updated"
        ]);
    }

    // Soft delete an application
    public function destroy($id)
    {
        $application = JobApplication::find($id);

        if (!$application) {
            return response()->json(["data" => null, "message" => "Application not found"], 404);
        }

        // We do not delete the file from Cloudinary on a soft delete to maintain historical records
        $application->delete();

        return response()->json([
            "data" => null,
            "message" => "Application archived successfully"
        ]);
    }
}
