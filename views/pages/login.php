<?php require_once __DIR__ . '/../components/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card bg-dark text-light">
                <div class="card-header">
                    <h2 class="text-center">Login</h2>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success">
                            Registration successful! Please login.
                        </div>
                    <?php endif; ?>
                    
                    <form id="loginForm" method="POST" action="/Spywalker/auth/login">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control bg-dark text-light" id="email" name="email" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control bg-dark text-light" id="password" name="password" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>

                    <div class="mt-3 text-center">
                        <p>Don't have an account? <a href="/Spywalker/register" class="text-primary">Register here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        let isValid = true;
        const errors = {};

        // Email validation
        const email = document.getElementById('email').value;
        if (!email.match(/^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]{2,7}$/)) {
            errors.email = 'Please enter a valid email address';
            isValid = false;
        }

        // Password validation
        const password = document.getElementById('password').value;
        if (!password) {
            errors.password = 'Password is required';
            isValid = false;
        }

        // Display errors or submit form
        if (!isValid) {
            Object.keys(errors).forEach(field => {
                const input = document.getElementById(field);
                if (input) {
                    input.classList.add('is-invalid');
                    input.nextElementSibling.textContent = errors[field];
                }
            });
        } else {
            // Submit form via AJAX
            const formData = new FormData(form);
            fetch('/Spywalker/auth/login', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/Spywalker/dashboard';
                } else {
                    alert(data.errors.join('\n'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during login');
            });
        }
    });
});
</script>

<?php require_once '../components/footer.php'; ?>
