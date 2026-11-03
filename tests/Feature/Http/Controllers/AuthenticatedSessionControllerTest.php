<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('shows the login form to guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Contraseña');
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('occupancy.index'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);

    $this->assertGuest();
});

it('rejects a disabled user even with valid credentials', function (Closure $crearUsuario) {
    $user = $crearUsuario();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Su usuario está deshabilitado.']);

    $this->assertGuest();
})->with([
    'inactive user' => fn () => User::factory()->inactivo()->create(),
    'inactive employee' => fn () => User::factory()->for(Employee::factory()->inactivo())->create(),
]);

it('throttles repeated failed attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertTooManyRequests();

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('sends guests to the login form', function () {
    $this->get(route('occupancy.index'))->assertRedirect(route('login'));
});
