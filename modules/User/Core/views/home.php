<?php

/** views/home.php */
?>
<div class="user-home-container">

    <header>
        <h1 id="welcome-title">Welcome, User!</h1>
    </header>

    <section class="user-info">
        <h2>User Information</h2>
        <div id="user-info-content">
            <p>Loading user data...</p>
        </div>
    </section>

    <section class="user-actions">
        <button class="btn-logout">Logout</button>
    </section>

</div>

<?php injectSpaScripts($spa_scripts ?? []); ?>

<script>
    document.addEventListener('click', async (e) => {
        if (e.target.matches('.btn-logout')) {
            e.preventDefault();

            if (typeof loadPage !== 'function') {
                console.warn('loadPage not ready yet, retrying...');
                setTimeout(() => e.target.click(), 100); // retry après 100ms
                return;
            }

            try {
                const res = await fetch('/auth/logout', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.csrfToken
                    }
                });
                if (!res.ok) throw new Error('Logout failed');

                // Charger la page login/auth en SPA
                loadPage('/auth', true);

            } catch (err) {
                console.error('Logout error:', err);
                alert('Logout failed. Try again.');
            }
        }
    });
</script>