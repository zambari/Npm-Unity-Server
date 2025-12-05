<?php

namespace App\Http\Controllers;

use App\Models\Scope;
use Illuminate\Http\Request;

class ScopeController extends Controller
{
    /**
     * Display a listing of scopes
     */
    public function index()
    {
        $scopes = Scope::withCount('packages')
            ->orderBy('scope', 'asc')
            ->get();
        return view('admin.scopes.index', compact('scopes'));
    }

    /**
     * Store a newly created scope
     */
    public function store(Request $request)
    {
        $request->validate([
            'scope' => [
                'required',
                'string',
                'max:45',
                'unique:scopes,scope',
                function ($attribute, $value, $fail) {
                    if (empty(trim($value))) {
                        $fail('The scope cannot be empty.');
                    }
                    if (strpos($value, '.') === false) {
                        $fail('The scope must contain at least one dot.');
                    }
                },
            ],
            'display_name' => 'nullable|string|max:255',
        ], [
            'scope.required' => 'The scope field is required.',
            'scope.unique' => 'This scope already exists.',
        ]);

        $scope = new Scope();
        $scope->scope = trim($request->scope);
        $scope->display_name = $request->display_name ? trim($request->display_name) : null;
        $scope->save();

        return redirect()->route('admin.scopes')->with('success', 'Scope created successfully.');
    }

    /**
     * Show the form for editing a scope
     */
    public function edit($id)
    {
        $scope = Scope::findOrFail($id);
        // Packages are loaded by the component, but we pass scope_id for reference
        return view('admin.scopes.edit', compact('scope'));
    }

    /**
     * Update a scope
     */
    public function update(Request $request, $id)
    {
        $scope = Scope::findOrFail($id);

        $request->validate([
            'scope' => [
                'required',
                'string',
                'max:45',
                'unique:scopes,scope,' . $scope->id,
                function ($attribute, $value, $fail) {
                    if (empty(trim($value))) {
                        $fail('The scope cannot be empty.');
                    }
                    if (strpos($value, '.') === false) {
                        $fail('The scope must contain at least one dot.');
                    }
                },
            ],
            'display_name' => 'nullable|string|max:255',
        ], [
            'scope.required' => 'The scope field is required.',
            'scope.unique' => 'This scope already exists.',
        ]);

        $scope->scope = trim($request->scope);
        $scope->display_name = $request->display_name ? trim($request->display_name) : null;
        $scope->save();

        return redirect()->route('admin.scopes')->with('success', 'Scope updated successfully.');
    }

    /**
     * Remove a scope
     */
    public function destroy($id)
    {
        $scope = Scope::findOrFail($id);
        
        // Check if scope has packages
        if ($scope->packages()->count() > 0) {
            return redirect()->route('admin.scopes')
                ->withErrors(['error' => 'Cannot delete scope. It has associated packages.']);
        }

        $scopeName = $scope->scope;
        $scope->delete();

        return redirect()->route('admin.scopes')->with('success', "Scope '{$scopeName}' has been deleted.");
    }
}

