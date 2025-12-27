<h1>New user</h1>

<!-- Zone message globale (optionnel) -->
<div class="alert alert-danger d-none" role="alert" id="user-create-global-error"></div>

<form
    data-spa-form
    action="/api/users"
    method="post"
    data-spa-disable="true"
    data-spa-reset="false"
    novalidate>

    <div class="mb-3">
        <label class="form-label">Name</label>
        <input class="form-control" type="text" name="name" required>
        <div class="invalid-feedback" data-err="name"></div>
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" required>
        <div class="invalid-feedback" data-err="email"></div>
    </div>

    <div class="mb-3">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required>
        <div class="invalid-feedback" data-err="password"></div>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="active" value="1" id="activeCheck">
        <label class="form-check-label" for="activeCheck">Active</label>
        <div class="invalid-feedback d-block" data-err="active"></div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Create</button>
        <a class="btn btn-outline-secondary" data-spa href="/users">Cancel</a>
    </div>
</form>