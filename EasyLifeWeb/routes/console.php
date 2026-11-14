<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sube a Firestore todos los datos de los usuarios vinculados con Firebase (util tras configurar Firebase)
Artisan::command('firebase:mirror {email? : Solo este usuario}', function (App\Services\Firebase\FirestoreMirror $mirror) {
    if (! $mirror->enabled()) {
        $this->error('Firebase no está configurado (FIREBASE_PROJECT_ID / FIREBASE_CREDENTIALS).');

        return 1;
    }
    $users = App\Models\User::whereNotNull('firebase_uid')
        ->when($this->argument('email'), fn ($q, $email) => $q->where('email', $email))->get();
    foreach ($users as $user) {
        $this->info("{$user->email}: ".$mirror->mirrorUser($user).' documento(s)');
    }

    return 0;
})->purpose('Copia los datos de los usuarios a Cloud Firestore');

// Usado al desplegar (docker/start.sh): siembra la base solo la primera vez
Artisan::command('app:seed-if-empty', function () {
    if (App\Models\User::exists()) {
        $this->info('La base ya tiene datos: no se siembra.');

        return 0;
    }
    $this->call('db:seed', ['--force' => true]);

    return 0;
})->purpose('Carga los datos iniciales si la base está vacía');
