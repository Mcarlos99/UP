import React, { useState, useEffect } from 'react';
import { 
  LayoutDashboard, Package, ShoppingBag, Users, DollarSign, 
  BarChart3, Plus, Search, Filter, Calendar, Eye, Edit2, 
  Trash2, CheckCircle, Clock, AlertCircle, X, ChevronDown,
  FileText, TrendingUp, Box, Menu, Settings, LogOut, Save
} from 'lucide-react';

// Componente Principal
const PapelariaSystem = () => {
  const [currentView, setCurrentView] = useState('dashboard');
  const [searchTerm, setSearchTerm] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [modalType, setModalType] = useState('');
  const [selectedItem, setSelectedItem] = useState(null);
  const [sidebarOpen, setSidebarOpen] = useState(true);

  // Estados para dados
  const [produtos, setProdutos] = useState([
    { id: 1, nome: 'Caderno Personalizado A5', categoria: 'Cadernos', preco: 45.00, estoque: 25, imagem: '📓' },
    { id: 2, nome: 'Agenda 2025 Luxo', categoria: 'Agendas', preco: 89.90, estoque: 15, imagem: '📅' },
    { id: 3, nome: 'Planner Semanal', categoria: 'Planners', preco: 65.00, estoque: 30, imagem: '📋' },
    { id: 4, nome: 'Kit Escritório Personalizado', categoria: 'Kits', preco: 120.00, estoque: 10, imagem: '🎁' },
    { id: 5, nome: 'Caneta Especial Nome', categoria: 'Canetas', preco: 15.00, estoque: 50, imagem: '🖊️' },
  ]);

  const [pedidos, setPedidos] = useState([
    { 
      id: 1, 
      cliente: 'Maria Silva', 
      produtos: 'Caderno Personalizado A5 (2x)', 
      valor: 90.00, 
      status: 'em_producao', 
      dataEntrega: '2025-01-25',
      dataPedido: '2025-01-18'
    },
    { 
      id: 2, 
      cliente: 'João Santos', 
      produtos: 'Agenda 2025 Luxo (1x)', 
      valor: 89.90, 
      status: 'aguardando', 
      dataEntrega: '2025-01-30',
      dataPedido: '2025-01-18'
    },
    { 
      id: 3, 
      cliente: 'Ana Costa', 
      produtos: 'Kit Escritório (1x)', 
      valor: 120.00, 
      status: 'concluido', 
      dataEntrega: '2025-01-20',
      dataPedido: '2025-01-15'
    },
    { 
      id: 4, 
      cliente: 'Pedro Oliveira', 
      produtos: 'Planner Semanal (3x)', 
      valor: 195.00, 
      status: 'em_producao', 
      dataEntrega: '2025-01-28',
      dataPedido: '2025-01-17'
    },
  ]);

  const [clientes, setClientes] = useState([
    { id: 1, nome: 'Maria Silva', telefone: '(11) 98765-4321', email: 'maria@email.com', totalCompras: 450.00, pedidos: 5 },
    { id: 2, nome: 'João Santos', telefone: '(11) 97654-3210', email: 'joao@email.com', totalCompras: 320.00, pedidos: 4 },
    { id: 3, nome: 'Ana Costa', telefone: '(11) 96543-2109', email: 'ana@email.com', totalCompras: 680.00, pedidos: 8 },
    { id: 4, nome: 'Pedro Oliveira', telefone: '(11) 95432-1098', email: 'pedro@email.com', totalCompras: 195.00, pedidos: 2 },
  ]);

  const [financeiro, setFinanceiro] = useState({
    receitas: [
      { id: 1, descricao: 'Venda - Maria Silva', valor: 90.00, data: '2025-01-18', tipo: 'receita' },
      { id: 2, descricao: 'Venda - João Santos', valor: 89.90, data: '2025-01-18', tipo: 'receita' },
    ],
    despesas: [
      { id: 1, descricao: 'Compra de papel', valor: 150.00, data: '2025-01-15', tipo: 'despesa' },
      { id: 2, descricao: 'Tinta para impressora', valor: 80.00, data: '2025-01-16', tipo: 'despesa' },
    ]
  });

  // Funções auxiliares
  const getStatusColor = (status) => {
    switch(status) {
      case 'aguardando': return 'bg-yellow-100 text-yellow-800 border-yellow-300';
      case 'em_producao': return 'bg-blue-100 text-blue-800 border-blue-300';
      case 'concluido': return 'bg-green-100 text-green-800 border-green-300';
      default: return 'bg-gray-100 text-gray-800 border-gray-300';
    }
  };

  const getStatusIcon = (status) => {
    switch(status) {
      case 'aguardando': return <Clock className="w-4 h-4" />;
      case 'em_producao': return <AlertCircle className="w-4 h-4" />;
      case 'concluido': return <CheckCircle className="w-4 h-4" />;
      default: return null;
    }
  };

  const getStatusText = (status) => {
    switch(status) {
      case 'aguardando': return 'Aguardando';
      case 'em_producao': return 'Em Produção';
      case 'concluido': return 'Concluído';
      default: return status;
    }
  };

  const calcularTotais = () => {
    const totalReceitas = financeiro.receitas.reduce((acc, item) => acc + item.valor, 0);
    const totalDespesas = financeiro.despesas.reduce((acc, item) => acc + item.valor, 0);
    return {
      receitas: totalReceitas,
      despesas: totalDespesas,
      lucro: totalReceitas - totalDespesas
    };
  };

  // Componente: Sidebar
  const Sidebar = () => (
    <div className={`fixed left-0 top-0 h-full bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 text-white transition-all duration-300 z-50 ${sidebarOpen ? 'w-64' : 'w-20'}`}>
      <div className="p-6">
        <div className="flex items-center justify-between mb-8">
          {sidebarOpen && <h1 className="text-2xl font-bold bg-gradient-to-r from-pink-200 to-purple-200 bg-clip-text text-transparent">PaperArt</h1>}
          <button onClick={() => setSidebarOpen(!sidebarOpen)} className="p-2 hover:bg-white/10 rounded-lg transition">
            <Menu className="w-5 h-5" />
          </button>
        </div>

        <nav className="space-y-2">
          {[
            { icon: LayoutDashboard, label: 'Dashboard', view: 'dashboard' },
            { icon: ShoppingBag, label: 'Pedidos', view: 'pedidos' },
            { icon: Package, label: 'Produtos', view: 'produtos' },
            { icon: Users, label: 'Clientes', view: 'clientes' },
            { icon: DollarSign, label: 'Financeiro', view: 'financeiro' },
            { icon: BarChart3, label: 'Relatórios', view: 'relatorios' },
          ].map((item) => (
            <button
              key={item.view}
              onClick={() => setCurrentView(item.view)}
              className={`w-full flex items-center gap-3 p-3 rounded-lg transition-all ${
                currentView === item.view 
                  ? 'bg-white text-purple-900 shadow-lg' 
                  : 'hover:bg-white/10'
              }`}
            >
              <item.icon className="w-5 h-5" />
              {sidebarOpen && <span className="font-medium">{item.label}</span>}
            </button>
          ))}
        </nav>
      </div>

      {sidebarOpen && (
        <div className="absolute bottom-0 left-0 right-0 p-6 border-t border-white/10">
          <button className="w-full flex items-center gap-3 p-3 hover:bg-white/10 rounded-lg transition">
            <Settings className="w-5 h-5" />
            <span>Configurações</span>
          </button>
        </div>
      )}
    </div>
  );

  // Componente: Dashboard
  const Dashboard = () => {
    const totais = calcularTotais();
    const pedidosPendentes = pedidos.filter(p => p.status !== 'concluido').length;
    const produtosBaixoEstoque = produtos.filter(p => p.estoque < 15).length;

    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Dashboard</h2>
            <p className="text-gray-600 mt-1">Visão geral do seu negócio</p>
          </div>
          <div className="text-right">
            <p className="text-sm text-gray-600">Hoje</p>
            <p className="text-lg font-semibold text-gray-900">18 de Janeiro, 2026</p>
          </div>
        </div>

        {/* Cards de métricas */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div className="bg-gradient-to-br from-purple-500 to-pink-500 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all hover:scale-105">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-purple-100 text-sm font-medium">Faturamento Mês</p>
                <p className="text-3xl font-bold mt-2">R$ {totais.receitas.toFixed(2)}</p>
                <p className="text-purple-100 text-xs mt-2 flex items-center gap-1">
                  <TrendingUp className="w-3 h-3" />
                  +12% vs mês anterior
                </p>
              </div>
              <div className="bg-white/20 p-3 rounded-xl">
                <DollarSign className="w-6 h-6" />
              </div>
            </div>
          </div>

          <div className="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all hover:scale-105">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-blue-100 text-sm font-medium">Pedidos Ativos</p>
                <p className="text-3xl font-bold mt-2">{pedidosPendentes}</p>
                <p className="text-blue-100 text-xs mt-2">
                  {pedidos.length} pedidos no total
                </p>
              </div>
              <div className="bg-white/20 p-3 rounded-xl">
                <ShoppingBag className="w-6 h-6" />
              </div>
            </div>
          </div>

          <div className="bg-gradient-to-br from-green-500 to-emerald-500 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all hover:scale-105">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-green-100 text-sm font-medium">Lucro Líquido</p>
                <p className="text-3xl font-bold mt-2">R$ {totais.lucro.toFixed(2)}</p>
                <p className="text-green-100 text-xs mt-2">
                  Margem: {((totais.lucro / totais.receitas) * 100).toFixed(1)}%
                </p>
              </div>
              <div className="bg-white/20 p-3 rounded-xl">
                <TrendingUp className="w-6 h-6" />
              </div>
            </div>
          </div>

          <div className="bg-gradient-to-br from-orange-500 to-red-500 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all hover:scale-105">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-orange-100 text-sm font-medium">Produtos</p>
                <p className="text-3xl font-bold mt-2">{produtos.length}</p>
                <p className="text-orange-100 text-xs mt-2 flex items-center gap-1">
                  <AlertCircle className="w-3 h-3" />
                  {produtosBaixoEstoque} com estoque baixo
                </p>
              </div>
              <div className="bg-white/20 p-3 rounded-xl">
                <Package className="w-6 h-6" />
              </div>
            </div>
          </div>
        </div>

        {/* Pedidos Recentes e Produtos Top */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Pedidos Recentes */}
          <div className="bg-white rounded-2xl shadow-lg p-6">
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-xl font-bold text-gray-900">Pedidos Recentes</h3>
              <button 
                onClick={() => setCurrentView('pedidos')}
                className="text-purple-600 hover:text-purple-700 text-sm font-medium"
              >
                Ver todos →
              </button>
            </div>
            <div className="space-y-4">
              {pedidos.slice(0, 4).map((pedido) => (
                <div key={pedido.id} className="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                  <div className="flex-1">
                    <p className="font-semibold text-gray-900">{pedido.cliente}</p>
                    <p className="text-sm text-gray-600">{pedido.produtos}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-bold text-gray-900">R$ {pedido.valor.toFixed(2)}</p>
                    <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border ${getStatusColor(pedido.status)}`}>
                      {getStatusIcon(pedido.status)}
                      {getStatusText(pedido.status)}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Produtos Mais Vendidos */}
          <div className="bg-white rounded-2xl shadow-lg p-6">
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-xl font-bold text-gray-900">Produtos Populares</h3>
              <button 
                onClick={() => setCurrentView('produtos')}
                className="text-purple-600 hover:text-purple-700 text-sm font-medium"
              >
                Ver todos →
              </button>
            </div>
            <div className="space-y-4">
              {produtos.slice(0, 4).map((produto, index) => (
                <div key={produto.id} className="flex items-center gap-4 p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                  <div className="text-4xl">{produto.imagem}</div>
                  <div className="flex-1">
                    <p className="font-semibold text-gray-900">{produto.nome}</p>
                    <p className="text-sm text-gray-600">{produto.categoria}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-bold text-purple-600">R$ {produto.preco.toFixed(2)}</p>
                    <p className="text-xs text-gray-600">Estoque: {produto.estoque}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    );
  };

  // Componente: Pedidos
  const Pedidos = () => {
    const [filtroStatus, setFiltroStatus] = useState('todos');

    const pedidosFiltrados = pedidos.filter(pedido => {
      const matchStatus = filtroStatus === 'todos' || pedido.status === filtroStatus;
      const matchSearch = pedido.cliente.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         pedido.produtos.toLowerCase().includes(searchTerm.toLowerCase());
      return matchStatus && matchSearch;
    });

    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Pedidos</h2>
            <p className="text-gray-600 mt-1">Gerencie todos os seus pedidos</p>
          </div>
          <button 
            onClick={() => { setModalType('pedido'); setShowModal(true); setSelectedItem(null); }}
            className="flex items-center gap-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all hover:scale-105"
          >
            <Plus className="w-5 h-5" />
            Novo Pedido
          </button>
        </div>

        {/* Filtros */}
        <div className="bg-white rounded-2xl shadow-lg p-6">
          <div className="flex flex-wrap gap-4">
            <div className="flex-1 min-w-[300px]">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  type="text"
                  placeholder="Buscar por cliente ou produto..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                />
              </div>
            </div>
            <div className="flex gap-2">
              {['todos', 'aguardando', 'em_producao', 'concluido'].map((status) => (
                <button
                  key={status}
                  onClick={() => setFiltroStatus(status)}
                  className={`px-4 py-3 rounded-xl font-medium transition ${
                    filtroStatus === status
                      ? 'bg-purple-600 text-white'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  {status === 'todos' ? 'Todos' : getStatusText(status)}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Lista de Pedidos */}
        <div className="grid gap-4">
          {pedidosFiltrados.map((pedido) => (
            <div key={pedido.id} className="bg-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-3">
                    <h3 className="text-xl font-bold text-gray-900">#{pedido.id.toString().padStart(4, '0')}</h3>
                    <span className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border ${getStatusColor(pedido.status)}`}>
                      {getStatusIcon(pedido.status)}
                      {getStatusText(pedido.status)}
                    </span>
                  </div>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <p className="text-gray-600">Cliente</p>
                      <p className="font-semibold text-gray-900">{pedido.cliente}</p>
                    </div>
                    <div>
                      <p className="text-gray-600">Produtos</p>
                      <p className="font-semibold text-gray-900">{pedido.produtos}</p>
                    </div>
                    <div>
                      <p className="text-gray-600">Data do Pedido</p>
                      <p className="font-semibold text-gray-900">{new Date(pedido.dataPedido).toLocaleDateString('pt-BR')}</p>
                    </div>
                    <div>
                      <p className="text-gray-600">Entrega</p>
                      <p className="font-semibold text-gray-900">{new Date(pedido.dataEntrega).toLocaleDateString('pt-BR')}</p>
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="text-right mr-4">
                    <p className="text-gray-600 text-sm">Valor Total</p>
                    <p className="text-2xl font-bold text-purple-600">R$ {pedido.valor.toFixed(2)}</p>
                  </div>
                  <button 
                    onClick={() => { setSelectedItem(pedido); setModalType('pedido'); setShowModal(true); }}
                    className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                  >
                    <Edit2 className="w-5 h-5" />
                  </button>
                  <button className="p-2 text-green-600 hover:bg-green-50 rounded-lg transition">
                    <Eye className="w-5 h-5" />
                  </button>
                  <button className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                    <Trash2 className="w-5 h-5" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  };

  // Componente: Produtos
  const Produtos = () => (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Produtos</h2>
          <p className="text-gray-600 mt-1">Gerencie seu catálogo de produtos</p>
        </div>
        <button 
          onClick={() => { setModalType('produto'); setShowModal(true); setSelectedItem(null); }}
          className="flex items-center gap-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all hover:scale-105"
        >
          <Plus className="w-5 h-5" />
          Novo Produto
        </button>
      </div>

      {/* Barra de Busca */}
      <div className="bg-white rounded-2xl shadow-lg p-6">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            type="text"
            placeholder="Buscar produtos..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
          />
        </div>
      </div>

      {/* Grid de Produtos */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {produtos.filter(p => p.nome.toLowerCase().includes(searchTerm.toLowerCase())).map((produto) => (
          <div key={produto.id} className="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition group">
            <div className="bg-gradient-to-br from-purple-100 to-pink-100 p-12 flex items-center justify-center">
              <span className="text-6xl">{produto.imagem}</span>
            </div>
            <div className="p-6">
              <div className="flex items-start justify-between mb-3">
                <div className="flex-1">
                  <h3 className="text-lg font-bold text-gray-900 mb-1">{produto.nome}</h3>
                  <p className="text-sm text-gray-600">{produto.categoria}</p>
                </div>
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${produto.estoque < 15 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}`}>
                  {produto.estoque} un.
                </span>
              </div>
              <div className="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                <p className="text-2xl font-bold text-purple-600">R$ {produto.preco.toFixed(2)}</p>
                <div className="flex gap-2">
                  <button 
                    onClick={() => { setSelectedItem(produto); setModalType('produto'); setShowModal(true); }}
                    className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                  >
                    <Edit2 className="w-4 h-4" />
                  </button>
                  <button className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );

  // Componente: Clientes
  const Clientes = () => (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Clientes</h2>
          <p className="text-gray-600 mt-1">Gerencie sua base de clientes</p>
        </div>
        <button 
          onClick={() => { setModalType('cliente'); setShowModal(true); setSelectedItem(null); }}
          className="flex items-center gap-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all hover:scale-105"
        >
          <Plus className="w-5 h-5" />
          Novo Cliente
        </button>
      </div>

      {/* Barra de Busca */}
      <div className="bg-white rounded-2xl shadow-lg p-6">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            type="text"
            placeholder="Buscar clientes..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
          />
        </div>
      </div>

      {/* Lista de Clientes */}
      <div className="grid gap-4">
        {clientes.filter(c => c.nome.toLowerCase().includes(searchTerm.toLowerCase())).map((cliente) => (
          <div key={cliente.id} className="bg-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4 flex-1">
                <div className="w-16 h-16 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                  {cliente.nome.charAt(0)}
                </div>
                <div className="flex-1 grid grid-cols-4 gap-4">
                  <div>
                    <p className="text-gray-600 text-sm">Nome</p>
                    <p className="font-semibold text-gray-900">{cliente.nome}</p>
                  </div>
                  <div>
                    <p className="text-gray-600 text-sm">Telefone</p>
                    <p className="font-semibold text-gray-900">{cliente.telefone}</p>
                  </div>
                  <div>
                    <p className="text-gray-600 text-sm">Total em Compras</p>
                    <p className="font-semibold text-purple-600">R$ {cliente.totalCompras.toFixed(2)}</p>
                  </div>
                  <div>
                    <p className="text-gray-600 text-sm">Pedidos</p>
                    <p className="font-semibold text-gray-900">{cliente.pedidos} pedidos</p>
                  </div>
                </div>
              </div>
              <div className="flex gap-2">
                <button 
                  onClick={() => { setSelectedItem(cliente); setModalType('cliente'); setShowModal(true); }}
                  className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                >
                  <Edit2 className="w-5 h-5" />
                </button>
                <button className="p-2 text-green-600 hover:bg-green-50 rounded-lg transition">
                  <Eye className="w-5 h-5" />
                </button>
                <button className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                  <Trash2 className="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );

  // Componente: Financeiro
  const Financeiro = () => {
    const totais = calcularTotais();
    const todasTransacoes = [...financeiro.receitas, ...financeiro.despesas].sort((a, b) => 
      new Date(b.data) - new Date(a.data)
    );

    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Financeiro</h2>
            <p className="text-gray-600 mt-1">Controle suas receitas e despesas</p>
          </div>
          <button 
            onClick={() => { setModalType('transacao'); setShowModal(true); setSelectedItem(null); }}
            className="flex items-center gap-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all hover:scale-105"
          >
            <Plus className="w-5 h-5" />
            Nova Transação
          </button>
        </div>

        {/* Cards de Resumo Financeiro */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-6 text-white shadow-xl">
            <div className="flex items-center justify-between mb-4">
              <p className="text-green-100 font-medium">Receitas</p>
              <TrendingUp className="w-6 h-6 text-green-100" />
            </div>
            <p className="text-4xl font-bold">R$ {totais.receitas.toFixed(2)}</p>
            <p className="text-green-100 text-sm mt-2">{financeiro.receitas.length} transações</p>
          </div>

          <div className="bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl p-6 text-white shadow-xl">
            <div className="flex items-center justify-between mb-4">
              <p className="text-red-100 font-medium">Despesas</p>
              <DollarSign className="w-6 h-6 text-red-100" />
            </div>
            <p className="text-4xl font-bold">R$ {totais.despesas.toFixed(2)}</p>
            <p className="text-red-100 text-sm mt-2">{financeiro.despesas.length} transações</p>
          </div>

          <div className="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl p-6 text-white shadow-xl">
            <div className="flex items-center justify-between mb-4">
              <p className="text-purple-100 font-medium">Lucro Líquido</p>
              <BarChart3 className="w-6 h-6 text-purple-100" />
            </div>
            <p className="text-4xl font-bold">R$ {totais.lucro.toFixed(2)}</p>
            <p className="text-purple-100 text-sm mt-2">
              Margem: {((totais.lucro / totais.receitas) * 100).toFixed(1)}%
            </p>
          </div>
        </div>

        {/* Lista de Transações */}
        <div className="bg-white rounded-2xl shadow-lg p-6">
          <h3 className="text-xl font-bold text-gray-900 mb-6">Transações Recentes</h3>
          <div className="space-y-3">
            {todasTransacoes.slice(0, 10).map((transacao) => (
              <div key={transacao.id} className="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                <div className="flex items-center gap-4">
                  <div className={`w-12 h-12 rounded-full flex items-center justify-center ${
                    transacao.tipo === 'receita' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'
                  }`}>
                    {transacao.tipo === 'receita' ? <TrendingUp className="w-6 h-6" /> : <DollarSign className="w-6 h-6" />}
                  </div>
                  <div>
                    <p className="font-semibold text-gray-900">{transacao.descricao}</p>
                    <p className="text-sm text-gray-600">{new Date(transacao.data).toLocaleDateString('pt-BR')}</p>
                  </div>
                </div>
                <div className="flex items-center gap-4">
                  <p className={`text-xl font-bold ${transacao.tipo === 'receita' ? 'text-green-600' : 'text-red-600'}`}>
                    {transacao.tipo === 'receita' ? '+' : '-'} R$ {transacao.valor.toFixed(2)}
                  </p>
                  <button className="p-2 text-gray-600 hover:bg-gray-200 rounded-lg transition">
                    <Edit2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  };

  // Componente: Relatórios
  const Relatorios = () => {
    const totais = calcularTotais();
    
    return (
      <div className="space-y-6">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Relatórios</h2>
          <p className="text-gray-600 mt-1">Análises e insights do seu negócio</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Vendas por Categoria */}
          <div className="bg-white rounded-2xl shadow-lg p-6">
            <h3 className="text-xl font-bold text-gray-900 mb-6">Produtos por Categoria</h3>
            <div className="space-y-4">
              {['Cadernos', 'Agendas', 'Planners', 'Kits', 'Canetas'].map((categoria, index) => {
                const quantidade = produtos.filter(p => p.categoria === categoria).length;
                const percentual = (quantidade / produtos.length) * 100;
                
                return (
                  <div key={categoria}>
                    <div className="flex justify-between mb-2">
                      <span className="text-gray-700 font-medium">{categoria}</span>
                      <span className="text-gray-600">{quantidade} produtos</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-3">
                      <div 
                        className="bg-gradient-to-r from-purple-500 to-pink-500 h-3 rounded-full transition-all"
                        style={{ width: `${percentual}%` }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Status dos Pedidos */}
          <div className="bg-white rounded-2xl shadow-lg p-6">
            <h3 className="text-xl font-bold text-gray-900 mb-6">Status dos Pedidos</h3>
            <div className="space-y-4">
              {[
                { status: 'aguardando', label: 'Aguardando', color: 'from-yellow-400 to-orange-400' },
                { status: 'em_producao', label: 'Em Produção', color: 'from-blue-400 to-cyan-400' },
                { status: 'concluido', label: 'Concluídos', color: 'from-green-400 to-emerald-400' },
              ].map(({ status, label, color }) => {
                const quantidade = pedidos.filter(p => p.status === status).length;
                const percentual = (quantidade / pedidos.length) * 100;
                
                return (
                  <div key={status}>
                    <div className="flex justify-between mb-2">
                      <span className="text-gray-700 font-medium">{label}</span>
                      <span className="text-gray-600">{quantidade} pedidos</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-3">
                      <div 
                        className={`bg-gradient-to-r ${color} h-3 rounded-full transition-all`}
                        style={{ width: `${percentual}%` }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Top Clientes */}
          <div className="bg-white rounded-2xl shadow-lg p-6">
            <h3 className="text-xl font-bold text-gray-900 mb-6">Top 5 Clientes</h3>
            <div className="space-y-4">
              {clientes
                .sort((a, b) => b.totalCompras - a.totalCompras)
                .slice(0, 5)
                .map((cliente, index) => (
                  <div key={cliente.id} className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white font-bold">
                        {index + 1}
                      </div>
                      <div>
                        <p className="font-semibold text-gray-900">{cliente.nome}</p>
                        <p className="text-sm text-gray-600">{cliente.pedidos} pedidos</p>
                      </div>
                    </div>
                    <p className="text-lg font-bold text-purple-600">R$ {cliente.totalCompras.toFixed(2)}</p>
                  </div>
              ))}
            </div>
          </div>

          {/* Resumo Mensal */}
          <div className="bg-gradient-to-br from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
            <h3 className="text-xl font-bold mb-6">Resumo do Mês</h3>
            <div className="space-y-4">
              <div className="flex justify-between items-center pb-3 border-b border-white/20">
                <span className="text-purple-100">Total de Pedidos</span>
                <span className="text-2xl font-bold">{pedidos.length}</span>
              </div>
              <div className="flex justify-between items-center pb-3 border-b border-white/20">
                <span className="text-purple-100">Ticket Médio</span>
                <span className="text-2xl font-bold">
                  R$ {(totais.receitas / pedidos.length).toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between items-center pb-3 border-b border-white/20">
                <span className="text-purple-100">Produtos Cadastrados</span>
                <span className="text-2xl font-bold">{produtos.length}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-purple-100">Clientes Ativos</span>
                <span className="text-2xl font-bold">{clientes.length}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  };

  // Componente: Modal
  const Modal = () => {
    if (!showModal) return null;

    return (
      <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
          <div className="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
            <h3 className="text-2xl font-bold text-gray-900">
              {selectedItem ? 'Editar' : 'Novo'} {modalType === 'pedido' ? 'Pedido' : modalType === 'produto' ? 'Produto' : modalType === 'cliente' ? 'Cliente' : 'Transação'}
            </h3>
            <button 
              onClick={() => { setShowModal(false); setSelectedItem(null); }}
              className="p-2 hover:bg-gray-100 rounded-lg transition"
            >
              <X className="w-6 h-6" />
            </button>
          </div>

          <div className="p-6">
            {modalType === 'pedido' && (
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Cliente</label>
                  <select className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option>Selecione um cliente</option>
                    {clientes.map(c => <option key={c.id}>{c.nome}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Produtos</label>
                  <input 
                    type="text" 
                    placeholder="Ex: Caderno Personalizado A5 (2x)"
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Valor</label>
                    <input 
                      type="number" 
                      step="0.01"
                      placeholder="0.00"
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                      <option value="aguardando">Aguardando</option>
                      <option value="em_producao">Em Produção</option>
                      <option value="concluido">Concluído</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Data de Entrega</label>
                  <input 
                    type="date"
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                  />
                </div>
              </div>
            )}

            {modalType === 'produto' && (
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Nome do Produto</label>
                  <input 
                    type="text" 
                    placeholder="Ex: Caderno Personalizado A5"
                    defaultValue={selectedItem?.nome}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Categoria</label>
                    <select 
                      defaultValue={selectedItem?.categoria}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    >
                      <option>Cadernos</option>
                      <option>Agendas</option>
                      <option>Planners</option>
                      <option>Kits</option>
                      <option>Canetas</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Emoji/Ícone</label>
                    <input 
                      type="text" 
                      placeholder="📓"
                      defaultValue={selectedItem?.imagem}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Preço</label>
                    <input 
                      type="number" 
                      step="0.01"
                      placeholder="0.00"
                      defaultValue={selectedItem?.preco}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Estoque</label>
                    <input 
                      type="number"
                      placeholder="0"
                      defaultValue={selectedItem?.estoque}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                  </div>
                </div>
              </div>
            )}

            {modalType === 'cliente' && (
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Nome Completo</label>
                  <input 
                    type="text" 
                    placeholder="Ex: Maria Silva"
                    defaultValue={selectedItem?.nome}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Telefone</label>
                    <input 
                      type="tel" 
                      placeholder="(00) 00000-0000"
                      defaultValue={selectedItem?.telefone}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input 
                      type="email" 
                      placeholder="email@exemplo.com"
                      defaultValue={selectedItem?.email}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                  </div>
                </div>
              </div>
            )}

            {modalType === 'transacao' && (
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                  <div className="grid grid-cols-2 gap-4">
                    <button className="p-4 border-2 border-green-500 bg-green-50 text-green-700 rounded-xl font-semibold hover:bg-green-100 transition">
                      + Receita
                    </button>
                    <button className="p-4 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                      - Despesa
                    </button>
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                  <input 
                    type="text" 
                    placeholder="Ex: Venda - Cliente X"
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Valor</label>
                    <input 
                      type="number" 
                      step="0.01"
                      placeholder="0.00"
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Data</label>
                    <input 
                      type="date"
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                  </div>
                </div>
              </div>
            )}

            <div className="flex gap-3 mt-6 pt-6 border-t border-gray-200">
              <button 
                onClick={() => { setShowModal(false); setSelectedItem(null); }}
                className="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition"
              >
                Cancelar
              </button>
              <button 
                onClick={() => { setShowModal(false); setSelectedItem(null); }}
                className="flex-1 flex items-center justify-center gap-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition"
              >
                <Save className="w-5 h-5" />
                {selectedItem ? 'Salvar Alterações' : 'Criar'}
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  // Render
  return (
    <div className="min-h-screen bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50">
      <Sidebar />
      
      <div className={`transition-all duration-300 ${sidebarOpen ? 'ml-64' : 'ml-20'}`}>
        <div className="p-8">
          {currentView === 'dashboard' && <Dashboard />}
          {currentView === 'pedidos' && <Pedidos />}
          {currentView === 'produtos' && <Produtos />}
          {currentView === 'clientes' && <Clientes />}
          {currentView === 'financeiro' && <Financeiro />}
          {currentView === 'relatorios' && <Relatorios />}
        </div>
      </div>

      <Modal />
    </div>
  );
};

export default PapelariaSystem;