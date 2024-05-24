<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

class DriverController extends Controller
{
    public function index()
    {
        // Pastikan pengguna telah diautentikasi
        if (Auth::check()) {
            // Ambil peran pengguna yang masuk
            $user = Auth::user();
            // Periksa apakah pengguna memiliki peran Upt atau Admin
            if ($user->hasRole('Upt') || $user->hasRole('Admin')) {
                // Tentukan ID Upt yang akan digunakan dalam kueri

                $uptId = $user->hasRole('Upt') ? $user->id : ($user->hasRole('Admin') ? $user->id_upt : null);

                // Ambil data pengguna dengan peran Driver terkait dengan Upt yang sesuai
                $drivers = User::role('Driver')->where('id_upt', $uptId)->paginate(10);

                // Kembalikan tampilan dengan data pengguna (drivers)
                return view('drivers.index', compact('drivers'));
            }
        }
    }




    public function search(Request $request)
    {
        $searchTerm = $request->input('search');

        $drivers = User::role('Driver')
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('address', 'like', '%' . $searchTerm . '%');
            })
            ->paginate(10);

        return view('drivers.index', compact('drivers'));
    }

    // Menampilkan form untuk membuat pengguna baru
    public function create()
    {
        $roles = Role::all();
        return view('drivers.create', ['roles' => $roles]);
    }

    // Menyimpan pengguna baru ke database
    public function store(Request $request)
    {
        // Handle image upload
        $image = $request->file('image');
        if ($image) {
            // Store the uploaded image in the 'avatars' directory
            $imageName = $image->store('avatars');
        } else {
            // Menentukan jalur gambar default berdasarkan gender
            $defaultImagePath = $request->gender == 'Male' ? 'assets/images/avatars/male.jpg' : 'assets/images/avatars/female.jpg';

            // Cek apakah file gambar default ada
            $defaultImageExists = file_exists(public_path($defaultImagePath));

            // Debugging: Dump hasil pemeriksaan
            // dd($defaultImageExists);

            // Nama file gambar default
            $defaultImageName = basename($defaultImagePath); // Misalnya, 'male.jpg'
            $imageName = 'avatars/' . $defaultImageName;

            // Cek apakah gambar tidak ada di direktori 'avatars'
            if (!Storage::disk('public')->exists($imageName)) {
                // Jalur lengkap ke gambar tujuan di storage publik
                $destinationPath = public_path('storage/' . $imageName);

                // Buat direktori tujuan jika belum ada
                if (!file_exists(dirname($destinationPath))) {
                    mkdir(dirname($destinationPath), 0755, true);
                }

                // Salin gambar default ke direktori 'avatars'
                $copySuccess = copy(public_path($defaultImagePath), $destinationPath);

                // Debugging: Dump hasil penyalinan
                // dd($copySuccess);
            }
        }
        // Validasi data yang diterima dari form
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'address' => 'required',
            'gender' => 'required',
            'phone_number' => 'required|unique:users',
        ]);

        $userId = Auth::id();

        // Simpan data pengguna baru ke dalam database
        $driver = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'gender' => $request->gender,
            'phone_number' => $request->phone_number,
            'images' => $imageName,
            'id_upt' => $userId, // Menambahkan id_upt dari pengguna yang sedang masuk
            'created_at' => Carbon::now(),
        ]);

        // Cetak variabel $admin
        //dd($admin);


        // Beri peran 'Root' kepada pengguna baru
        $role = Role::findByName('Driver');
        $driver->assignRole($role);

        // Redirect ke halaman daftar pengguna
        return redirect()->route('drivers.index')->with('message', 'Berhasil menambah data');
    }


    // Menampilkan form untuk mengedit pengguna
    public function edit($id)
    {
        $driver = User::findOrFail($id);
        $roles = Role::all();
        $genders = [
            'male' => 'Laki-Laki',
            'female' => 'Perempuan'
        ];
        return view('drivers.edit', ['driver' => $driver, 'roles' => $roles, 'genders' => $genders]);
    }


    public function detail($id)
    {
        $driver = User::findOrFail($id);
        $roles = Role::all();
        $genders = [
            'male' => 'Laki-Laki',
            'female' => 'Perempuan'
        ];
        return view('drivers.detail', ['driver' => $driver, 'roles' => $roles, 'genders' => $genders]);
    }



    public function update(Request $request, $id)
    {
        // Validasi data yang diterima dari form
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|min:8',
            'address' => 'required',
            'gender' => 'required',
            'phone_number' => 'required|unique:users,phone_number,' . $id,
        ]);


        // Ambil data pengguna yang akan diupdate
        $driver = User::findOrFail($id);

        // Periksa apakah ada file gambar yang diunggah
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            Storage::delete($driver->images);

            // Simpan file gambar baru ke dalam penyimpanan yang diinginkan
            $imageName = $request->file('image')->store('avatars');

            // Update nama file gambar dalam database
            $driver->images = $imageName;
        }

        // Update data pengguna
        $driver->name = $request->name;
        $driver->email = $request->email;
        if ($request->filled('password')) {
            $driver->password = Hash::make($request->password);
        }
        $driver->address = $request->address;
        $driver->gender = $request->gender;
        $driver->phone_number = $request->phone_number;
        $driver->save();

        // Redirect ke halaman daftar pengguna dengan pesan sukses
        return redirect()->route('drivers.index')->with('message', 'Berhasil mengubah data.');
    }



    public function destroyMulti(Request $request)
    {
        // Validasi data yang diterima
        $request->validate([
            'ids' => 'required|array', // Pastikan ids adalah array
            'ids.*' => 'exists:users,id', // Pastikan setiap id ada dalam basis data Anda
        ]);

        // Lakukan penghapusan data berdasarkan ID yang diterima
        User::whereIn('id', $request->ids)->delete();

        // Redirect ke halaman sebelumnya atau halaman lain yang sesuai
        return redirect()->route('drivers.index')->with('message', 'Berhasil menghapus data');
    }
}
