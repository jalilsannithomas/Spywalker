<?php require_once __DIR__ . '/../components/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card bg-dark text-light">
                <div class="card-header">
                    <h2 class="text-center">Register</h2>
                </div>
                <div class="card-body">
                    <form action="/Spywalker/auth/register" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control bg-dark text-light" id="username" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control bg-dark text-light" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control bg-dark text-light" id="password" name="password" required>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select bg-dark text-light" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="athlete">Athlete</option>
                                <option value="coach">Coach</option>
                                <option value="fan">Fan</option>
                            </select>
                        </div>

                        <div id="athleteFields" style="display: none;">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control bg-dark text-light" id="first_name" name="first_name">
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control bg-dark text-light" id="last_name" name="last_name">
                            </div>
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control bg-dark text-light" id="date_of_birth" name="date_of_birth">
                            </div>
                            <div class="mb-3">
                                <label for="height" class="form-label">Height (cm)</label>
                                <input type="number" class="form-control bg-dark text-light" id="height" name="height">
                            </div>
                            <div class="mb-3">
                                <label for="weight" class="form-label">Weight (kg)</label>
                                <input type="number" class="form-control bg-dark text-light" id="weight" name="weight">
                            </div>
                        </div>

                        <div id="coachFields" style="display: none;">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control bg-dark text-light" id="first_name" name="first_name">
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control bg-dark text-light" id="last_name" name="last_name">
                            </div>
                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control bg-dark text-light" id="specialization" name="specialization">
                            </div>
                            <div class="mb-3">
                                <label for="experience_years" class="form-label">Years of Experience</label>
                                <input type="number" class="form-control bg-dark text-light" id="experience_years" name="experience_years">
                            </div>
                            <div class="mb-3">
                                <label for="certification" class="form-label">Certifications</label>
                                <textarea class="form-control bg-dark text-light" id="certification" name="certification"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control bg-dark text-light" id="bio" name="bio"></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>
                    <div class="mt-3 text-center">
                        <p>Already have an account? <a href="/Spywalker/login" class="text-primary">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const athleteFields = document.getElementById('athleteFields');
    const coachFields = document.getElementById('coachFields');

    roleSelect.addEventListener('change', function() {
        athleteFields.style.display = this.value === 'athlete' ? 'block' : 'none';
        coachFields.style.display = this.value === 'coach' ? 'block' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
