    </div> <!-- Fin du conteneur principal -->
    
    <!-- Footer principal -->
    <?php if (!isset($hide_footer) || !$hide_footer): ?>
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Digital YOURHOPE</h3>
                    <p>Votre partenaire éducatif numérique.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Plateforme</h4>
                    <a href="<?php echo SITE_URL; ?>platform/schools.php">Écoles</a>
                    <a href="<?php echo SITE_URL; ?>platform/teachers.php">Enseignants</a>
                    <a href="<?php echo SITE_URL; ?>platform/search.php">Recherche avancée</a>
                </div>
                
                <div class="footer-section">
                    <h4>Compte</h4>
                    <a href="<?php echo SITE_URL; ?>auth/login.php">Connexion</a>
                    <a href="<?php echo SITE_URL; ?>auth/register.php">Inscription</a>
                    <a href="<?php echo SITE_URL; ?>auth/register.php?type=school">École</a>
                    <a href="<?php echo SITE_URL; ?>auth/register.php?type=teacher">Enseignant</a>
                    <a href="<?php echo SITE_URL; ?>auth/register.php?type=parent">Parent</a>
                </div>
                
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p><i class="fas fa-envelope"></i> contact@digitalyourhope.com</p>
                    <p><i class="fas fa-phone"></i> +221 33 123 4567</p>
                    <p><i class="fas fa-map-marker-alt"></i> Dakar, Sénégal</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 Digital YOURHOPE. Tous droits réservés.</p>
                <div class="footer-links">
                    <a href="#">Politique de confidentialité</a>
                    <a href="#">Conditions d'utilisation</a>
                    <a href="#">Mentions légales</a>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    
    <!-- Scripts JavaScript -->
    <script src="<?php echo SITE_URL; ?>assets/js/main.js"></script>
    <?php if (isset($additional_js)): ?>
        <script src="<?php echo $additional_js; ?>"></script>
    <?php endif; ?>
    
    <?php if (isset($custom_scripts)): ?>
        <script>
            <?php echo $custom_scripts; ?>
        </script>
    <?php endif; ?>
    
    <!-- Messages de notification -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="notification success" style="position: fixed; top: 20px; right: 20px; padding: 15px 20px; background: #2ecc71; color: white; border-radius: 5px; z-index: 9999; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <script>
            setTimeout(() => {
                document.querySelector('.notification.success').style.opacity = '0';
                setTimeout(() => document.querySelector('.notification.success').remove(), 300);
            }, 3000);
        </script>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="notification error" style="position: fixed; top: 20px; right: 20px; padding: 15px 20px; background: #e74c3c; color: white; border-radius: 5px; z-index: 9999; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
        <script>
            setTimeout(() => {
                document.querySelector('.notification.error').style.opacity = '0';
                setTimeout(() => document.querySelector('.notification.error').remove(), 300);
            }, 3000);
        </script>
    <?php endif; ?>
    
</body>
</html>
<?php
// Fermer la mise en mémoire tampon si elle est active
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>