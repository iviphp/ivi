<h1>New user</h1>
<form action="/users" method="post">
    <div>
        <label>Name</label><br>
        <input type="text" name="name" required>
    </div>

    <div>
        <label>Email</label><br>
        <input type="email" name="email" required>
    </div>

    <div>
        <label>Password</label><br>
        <input type="password" name="password" required>
    </div>

    <div>
        <label><input type="checkbox" name="active" value="1"> Active</label>
    </div>

    <button type="submit">Create</button>
    <a href="/users">Cancel</a>
</form>