<?php
// app/views/proveedor/contacto.php - VERSIÓN SIMPLIFICADA
?>
<div class="page-container">
    <h1>📞 Contacto y Soporte</h1>
    
    <div class="contact-grid">
        <!-- Tarjeta de Teléfonos -->
        <div class="contact-card">
            <div class="contact-icon">📞</div>
            <h2>Teléfonos</h2>
            <p><strong>Departamento de Compras:</strong><br>
            (502) 2319-3200 ext. 1234</p>
            <p><strong>Departamento de Cuentas por Pagar:</strong><br>
            (502) 2319-3200 ext. 5678</p>
            <p><strong>Soporte Técnico Portal:</strong><br>
            (502) 2319-3200 ext. 9012</p>
        </div>

        <!-- Tarjeta de Correos -->
        <div class="contact-card">
            <div class="contact-icon">✉️</div>
            <h2>Correos Electrónicos</h2>
            <p><strong>Facturación y Pagos:</strong><br>
            <a href="mailto:facturas@agrocentro.com">facturas@agrocentro.com</a></p>
            <p><strong>Soporte Portal:</strong><br>
            <a href="mailto:soporte.proveedores@agrocentro.com">soporte.proveedores@agrocentro.com</a></p>
            <p><strong>Atención al Proveedor:</strong><br>
            <a href="mailto:proveedores@agrocentro.com">proveedores@agrocentro.com</a></p>
        </div>

        <!-- Tarjeta de Dirección -->
        <div class="contact-card">
            <div class="contact-icon">📍</div>
            <h2>Dirección</h2>
            <p>11 calle 6-44 zona 10<br>
            Oficina 701 Edificio Airali<br>
            Guatemala, Guatemala</p>
            <p><strong>Horario de Atención:</strong><br>
            Lunes a Viernes: 8:00 - 17:00<br>
            Recepción de Facturas: Lunes 8:00 - 15:00</p>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center;">
        <a href="index.php?controller=proveedor&action=dashboard" class="btn-secondary">← Volver al Dashboard</a>
    </div>
</div>

<style>
.contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.contact-card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s;
}

.contact-card:hover {
    transform: translateY(-5px);
}

.contact-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}

.contact-card h2 {
    color: #006400;
    margin-bottom: 15px;
    font-size: 1.3rem;
}

.contact-card p {
    margin: 10px 0;
    line-height: 1.5;
}

.contact-card a {
    color: #006400;
    text-decoration: none;
}

.contact-card a:hover {
    text-decoration: underline;
}
</style>