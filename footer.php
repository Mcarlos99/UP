</main>
        <!-- Footer -->
        <footer class="px-4 md:px-8 py-6 bg-white mt-auto">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <p class="text-gray-600 text-xs md:text-sm text-center md:text-left">
                    &copy; <?php echo date('Y'); ?> PaperArt - Sistema de Gestão para Papelaria
                </p>
                <div class="flex items-center gap-4">
                    <a href="#" class="text-gray-600 hover:text-purple-600 text-xs md:text-sm">Ajuda</a>
                    <a href="#" class="text-gray-600 hover:text-purple-600 text-xs md:text-sm">Suporte</a>
                    <a href="#" class="text-gray-600 hover:text-purple-600 text-xs md:text-sm">Termos de Uso</a>
                </div>
            </div>
        </footer>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Confirmação de exclusão
        function confirmarExclusao(mensagem = 'Tem certeza que deseja excluir este item?') {
            return confirm(mensagem);
        }

        // Toast notification
        function showToast(message, type = 'success') {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Auto-fechar alertas
        document.querySelectorAll('.alert-auto-close').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });

        // Formatação de valores monetários
        function formatarMoeda(input) {
            let value = input.value.replace(/\D/g, '');
            value = (parseFloat(value) / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            input.value = 'R$ ' + value;
        }

        // Máscara para telefone
        function mascaraTelefone(input) {
            let value = input.value.replace(/\D/g, '');
            
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
            }
            
            input.value = value;
        }

        // Máscara para CEP
        function mascaraCEP(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            input.value = value;
        }

        // Máscara para CPF/CNPJ
        function mascaraCPFCNPJ(input) {
            let value = input.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                // CPF
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            } else {
                // CNPJ
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            }
            
            input.value = value;
        }

        // Aplicar máscaras automaticamente
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[data-mask="telefone"]').forEach(input => {
                input.addEventListener('input', () => mascaraTelefone(input));
            });
            
            document.querySelectorAll('input[data-mask="cep"]').forEach(input => {
                input.addEventListener('input', () => mascaraCEP(input));
            });
            
            document.querySelectorAll('input[data-mask="cpf-cnpj"]').forEach(input => {
                input.addEventListener('input', () => mascaraCPFCNPJ(input));
            });
        });
    </script>
    
    <?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>