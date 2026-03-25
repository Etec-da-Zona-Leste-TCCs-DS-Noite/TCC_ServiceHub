<?php
// index.php - Página de entrada principal com carrossel
session_start();
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Sistema de Gestão de Serviços</title>
    <link rel="stylesheet" href="css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="main-header">
            <div class="header-content">
                <div class="logo">
                    <h1>ServiceHub</h1>
                    <p>Gestão de Serviços e Orçamentos</p>
                </div>
                <nav class="main-nav">
                    <ul>
                        <li><a href="index.php">Início</a></li>
                        <li><a href="servicos/index.php">Serviços</a></li>
                        <li><a href="clientes/index.php">Clientes</a></li>
                        <li><a href="orcamentos/index.php">Orçamentos</a></li>
                        <li><a href="relatorios/index.php">Relatórios</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <!-- Carrossel -->
        <div class="carousel-container">
            <button class="carousel-prev" onclick="prevSlide()">❮</button>
            <div class="carousel-slides" id="carouselSlides">
                <div class="carousel-slide">
                    <img src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=1200&h=500&fit=crop" alt="Gestão de Serviços">
                    <div class="carousel-caption">
                        <h2>Gestão Completa de Serviços</h2>
                        <p>Organize e gerencie todos os serviços da sua empresa em um só lugar</p>
                        <a href="servicos/index.php" class="carousel-btn">Conheça nossos serviços</a>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=1200&h=500&fit=crop" alt="Orçamentos">
                    <div class="carousel-caption">
                        <h2>Crie Orçamentos Profissionais</h2>
                        <p>Elabore orçamentos personalizados e acompanhe aprovações</p>
                        <a href="orcamentos/create.php" class="carousel-btn">Criar orçamento</a>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&h=500&fit=crop" alt="Relatórios">
                    <div class="carousel-caption">
                        <h2>Relatórios Detalhados</h2>
                        <p>Visualize estatísticas e tome decisões baseadas em dados</p>
                        <a href="relatorios/index.php" class="carousel-btn">Ver relatórios</a>
                    </div>
                </div>
            </div>
            <button class="carousel-next" onclick="nextSlide()">❯</button>
            <div class="carousel-dots" id="carouselDots"></div>
        </div>

        <!-- Features Section -->
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">💼</div>
                <h3>Gestão de Serviços</h3>
                <p>Cadastre e gerencie todos os serviços oferecidos pela sua empresa com preços e categorias.</p>
                <a href="servicos/index.php" class="btn">Gerenciar Serviços →</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Clientes</h3>
                <p>Gerencie sua base de clientes, histórico de contratações e informações de contato.</p>
                <a href="clientes/index.php" class="btn">Gerenciar Clientes →</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Orçamentos</h3>
                <p>Crie orçamentos personalizados com múltiplos serviços e acompanhe aprovações.</p>
                <a href="orcamentos/index.php" class="btn">Gerenciar Orçamentos →</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Relatórios</h3>
                <p>Visualize estatísticas completas sobre serviços, orçamentos e desempenho.</p>
                <a href="relatorios/index.php" class="btn">Ver Relatórios →</a>
            </div>
        </div>

        <!-- Stats Section -->
        <?php
        $totalServicos = $pdo->query("SELECT COUNT(*) FROM servicos")->fetchColumn();
        $totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
        $totalOrcamentos = $pdo->query("SELECT COUNT(*) FROM orcamentos")->fetchColumn();
        $aprovados = $pdo->query("SELECT COUNT(*) FROM orcamentos WHERE status = 'aprovado'")->fetchColumn();
        $valorTotal = $pdo->query("SELECT SUM(valor_total) FROM orcamentos WHERE status = 'aprovado'")->fetchColumn();
        ?>
        
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-number"><?= $totalServicos ?></div>
                <div class="stat-label">Serviços Cadastrados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalClientes ?></div>
                <div class="stat-label">Clientes Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalOrcamentos ?></div>
                <div class="stat-label">Orçamentos Realizados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $aprovados ?></div>
                <div class="stat-label">Orçamentos Aprovados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">R$ <?= number_format($valorTotal ?? 0, 2, ',', '.') ?></div>
                <div class="stat-label">Valor Total Aprovado</div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="main-footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>ServiceHub</h3>
                    <p>Sistema completo para gestão de serviços e orçamentos. Simplifique sua gestão e aumente sua produtividade.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-github"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Links Rápidos</h4>
                    <ul>
                        <li><a href="servicos/index.php">Serviços</a></li>
                        <li><a href="clientes/index.php">Clientes</a></li>
                        <li><a href="orcamentos/index.php">Orçamentos</a></li>
                        <li><a href="relatorios/index.php">Relatórios</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Suporte</h4>
                    <ul>
                        <li><a href="#">Central de Ajuda</a></li>
                        <li><a href="#">Documentação</a></li>
                        <li><a href="#">API</a></li>
                        <li><a href="#">Contato</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contato</h4>
                    <p><i class="fas fa-envelope"></i> contato@servicehub.com.br</p>
                    <p><i class="fas fa-phone"></i> (11) 4000-0000</p>
                    <p><i class="fas fa-clock"></i> Seg-Sex: 8h às 18h</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> ServiceHub - Todos os direitos reservados. | Desenvolvido com ❤️ para sua empresa</p>
            </div>
        </footer>
    </div>

    <script>
        // Carrossel JavaScript
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const totalSlides = slides.length;
        const slidesContainer = document.getElementById('carouselSlides');
        const dotsContainer = document.getElementById('carouselDots');
        
        // Criar dots
        for (let i = 0; i < totalSlides; i++) {
            const dot = document.createElement('div');
            dot.classList.add('dot');
            dot.onclick = () => goToSlide(i);
            dotsContainer.appendChild(dot);
        }
        
        function updateDots() {
            const dots = document.querySelectorAll('.dot');
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
            });
        }
        
        function updateCarousel() {
            slidesContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
            updateDots();
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            updateCarousel();
        }
        
        function prevSlide() {
            currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
            updateCarousel();
        }
        
        function goToSlide(index) {
            currentSlide = index;
            updateCarousel();
        }
        
        // Auto slide a cada 5 segundos
        let autoSlide = setInterval(nextSlide, 5000);
        
        // Pausar auto slide quando o mouse está sobre o carrossel
        const carousel = document.querySelector('.carousel-container');
        carousel.addEventListener('mouseenter', () => clearInterval(autoSlide));
        carousel.addEventListener('mouseleave', () => {
            autoSlide = setInterval(nextSlide, 5000);
        });
        
        // Inicializar
        updateCarousel();
    </script>
</body>
</html>