<?php require_once __DIR__ . '/../components/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center text-center">
        <div class="col-md-8">
            <h1 class="display-4 mb-4" style="color: var(--neon-blue);">Welcome to SpyWalker</h1>
            <p class="lead mb-4">Connect with Ashesi University athletes, coaches, and sports enthusiasts.</p>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                    <a href="/Spywalker/register" class="btn btn-primary btn-lg px-4 gap-3">Get Started</a>
                    <a href="/Spywalker/login" class="btn btn-outline-light btn-lg px-4">Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="card-title" style="color: var(--neon-purple);">Athletes</h3>
                    <p class="card-text">Create your profile, showcase your achievements, and connect with coaches.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="card-title" style="color: var(--neon-green);">Coaches</h3>
                    <p class="card-text">Discover talented athletes, manage your teams, and track performance.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="card-title" style="color: var(--neon-blue);">Fantasy Sports</h3>
                    <p class="card-text">Create your dream team and compete with other fans in our fantasy league.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
