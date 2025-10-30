<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatabaseController extends Controller
{
    // ✅ SELECT (Login + General Select)
    public function select(Request $request)
    {
        $table = $request->input('table');
        $conditions = $request->input('conditions', []);

        try {
            // ✅ Special case: login (email + password)
            if ($table === 'users' && isset($conditions['email']) && isset($conditions['password'])) {
                $email = $conditions['email'];
                $password = md5($conditions['password']);

                $user = DB::table('users')
                    ->where('email', $email)
                    ->where('password', $password)
                    ->first();

                if ($user) {
                    unset($user->password);

                    // ✅ Log the login action using user ID from database
                    $this->logAction(
                        'login',
                        'users',
                        $user->id,
                        "User {$user->email} logged in successfully",
                        $user->id
                    );

                    return response()->json([
                        'success' => true,
                        'message' => 'Login successful',
                        'data'    => [$user],
                    ]);
                }

                // ❌ invalid login
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                    'data'    => [],
                ]);
            }

            // ✅ Default: regular SELECT
            $query = DB::table($table);
            foreach ($conditions as $col => $val) {
                $query->where($col, $val);
            }

            $results = $query->get();

            if ($results->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No records found',
                    'data'    => [],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Records fetched successfully',
                'data'    => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ✅ INSERT
    public function insert(Request $request)
    {
        $table = $request->input('table');
        $data  = $request->input('data', []);
        $userId = $request->input('user_id_session');

        try {
            // 🔒 Hash password if present
            if (isset($data['password'])) {
                $data['password'] = md5($data['password']);
            }

            // Prevent accidental insertion of primary key value
            // If frontend sends an `id` field (empty or otherwise), remove it so DB can auto-increment.
            if (isset($data['id'])) {
                unset($data['id']);
            }

            // Handle file uploads
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('uploads', 'public');
                $data['file_path'] = $path;
            }

            $id = DB::table($table)->insertGetId($data);

            $this->logAction('insert', $table, $id, "Inserted new record into {$table}", $userId);

            return response()->json([
                'success' => true,
                'message' => 'Record inserted successfully',
                'data'    => [
                    'id'        => $id,
                    'file_path' => $data['file_path'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Insert failed: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ✅ UPDATE
    public function update(Request $request)
    {
        $table      = $request->input('table');
        $conditions = $request->input('conditions', $request->input('where', []));
        $data       = $request->input('data', []);
        $userId     = $request->input('user_id_session');

        try {
            if (empty($conditions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Update failed: No conditions provided',
                    'data'    => [],
                ], 400);
            }

            if (isset($data['password'])) {
                $data['password'] = md5($data['password']);
            }

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('uploads', 'public');
                $data['file_path'] = $path;
            }

            if (isset($data['email']) && isset($conditions['id'])) {
                $exists = DB::table($table)
                    ->where('email', $data['email'])
                    ->where('id', '!=', $conditions['id'])
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email already used by another account',
                        'data'    => [],
                    ], 422);
                }
            }

            $query = DB::table($table);
            foreach ($conditions as $col => $val) {
                $query->where($col, $val);
            }

            $affected = $query->update($data);

            if ($affected > 0) {
                $this->logAction('update', $table, $conditions['id'] ?? null, "Updated {$affected} record(s) in {$table}", $userId);

                return response()->json([
                    'success' => true,
                    'message' => 'Record(s) updated successfully',
                    'data'    => [
                        'affected'  => $affected,
                        'file_path' => $data['file_path'] ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No records updated (check conditions)',
                'data'    => [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ✅ DELETE
    public function delete(Request $request)
    {
        $table      = $request->input('table');
        $conditions = $request->input('conditions', []);
        $userId     = $request->input('user_id_session');

        try {
            $query = DB::table($table);
            foreach ($conditions as $col => $val) {
                $query->where($col, $val);
            }

            $deleted = $query->delete();

            if ($deleted > 0) {
                $this->logAction('delete', $table, null, "Deleted {$deleted} record(s) from {$table}", $userId);

                return response()->json([
                    'success' => true,
                    'message' => 'Record(s) deleted successfully',
                    'data'    => ['deleted' => $deleted],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No records deleted (check conditions)',
                'data'    => [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ✅ CUSTOM SQL
    public function custom(Request $request)
    {
        $sql      = $request->input('sql');
        $bindings = $request->input('bindings', []);
        $userId   = $request->input('user_id_session');

        try {
            $result = DB::select($sql, $bindings);

            $this->logAction('custom', 'raw_sql', null, "Executed custom SQL: {$sql}", $userId);

            return response()->json([
                'success' => true,
                'message' => 'Custom query executed successfully',
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Custom query failed: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ✅ LOG ACTION
    private function logAction($action, $table, $recordId = null, $message = '', $userId = null)
    {
        DB::table('logs')->insert([
            'user_id'    => $userId,
            'action'     => $action,
            'table_name' => $table,
            'record_id'  => $recordId,
            'message'    => $message,
            'created_at' => now(),
        ]);
    }
}
