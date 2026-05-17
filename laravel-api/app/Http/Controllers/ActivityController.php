<?php

namespace App\Http\Controllers;

// use App\Http\Requests\StoreActivityRequest;
// use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\StatusResource;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all activities
        $activities = Activity::with(['user'])->latest()->get();

        //return collection of Activities as a resource
        return new StatusResource(true, 'List Data Activities', $activities);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //define validation rules
        $validator = Validator::make($request->all(), [
            'table_name'        => ['required', 'min:3'],
            'record_id'         => ['required', 'min:1'],
            'information'       => ['required', 'min:3', 'max:100'],
        ]);

        //check if validation fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        //create activities
        $activities = Activity::create([
            'user_id'       => auth()->id(),
            'before'        => $request->before,
            'after'         => $request->after,
            'new'           => $request->new,
            'delete'        => $request->delete,
            'table_name'    => $request->table_name,
            'record_id'     => $request->record_id,
            'information'   => $request->information,
        ]);

        //return response
        return new StatusResource(true, 'Data Activities Baru Berhasil Ditambahkan!', $activities);
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Activity $activity)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($id)
    {
        return DB::transaction(function () use ($id) {
            $activity = Activity::findOrFail($id);
            if (isset($activity)) {
                // Gunakan DB Transaction untuk memastikan integritas data
                $tableName = $activity->table_name; // Nama tabel tujuan
                $recordId = $activity->record_id;   // ID data asli

                // Variabel untuk menampung data log baru
                $logData = [
                    'user_id'    => auth()->id(),
                    'table_name' => $tableName,
                    'record_id'  => $recordId,
                    'before'     => null,
                    'after'      => null,
                    'new'        => null,
                    'delete'     => null,
                    'information'=> ''
                ];

                // Ambil Nama Model secara dinamis berdasarkan table_name
                // Contoh: 'users' menjadi 'App\Models\User'
                $modelName = 'App\\Models\\' . \Illuminate\Support\Str::studly(\Illuminate\Support\Str::singular($tableName));

                switch ($activity->information) {
                    case 'Updated':
                        // Kembalikan ke data 'before'
                        DB::table($modelName)->where('id', $recordId)->update($activity->before);

                        $logData['before'] = $activity->after; // Data yang salah
                        $logData['after']  = $activity->before; // Data yang dikembalikan
                        $logData['information'] = 'Rollbacked_Updated';
                        break;

                    case 'Deleted':
                        /** * LOGIC SOFT DELETE RESTORE
                         * Kita cari data yang sudah di-soft delete (withTrashed) berdasarkan ID asli
                         */
                        $data = $modelName::withTrashed()->find($recordId);

                        if ($data) {
                            $data->restore(); // Mengosongkan kolom deleted_at

                            $logData['new'] = $data->toArray();
                            $logData['information'] = 'Rollbacked_Deleted';
                        } else {
                            throw new \Exception("Data yang akan direstore tidak ditemukan di tabel $tableName");
                        }
                        break;

                    case 'Created':
                        /**
                         * LOGIC SOFT DELETE UNTUK DATA BARU
                         * Rollback dari 'Created' adalah menghapusnya kembali.
                         * Karena kita pakai Soft Deletes, maka ini akan menjadi soft delete.
                         */
                        $data = $modelName::find($recordId);

                        if ($data) {
                            $logData['before'] = $data->toArray();
                            $data->delete(); // Ini otomatis melakukan soft delete

                            $logData['information'] = 'Rollbacked_Created';
                        } else {
                            throw new \Exception("Data aktif sudah tidak ditemukan di tabel $tableName");
                        }
                        break;
                }
            } else {
                return response()->json(['message' => 'Data Activity Tidak Ditemukan!'], 404);
            }

            // Cukup satu kali Create Activity di akhir
            $newActivity = Activity::create($logData);

            //return response
            return new StatusResource(true, 'Data Rollbacked Activity Berhasil Ditambahkan!', $newActivity);

        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        //
    }
}
