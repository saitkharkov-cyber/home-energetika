<?php echo $header; ?>
<div class="container">
	<ul class="breadcrumb">
		<?php foreach ($breadcrumbs as $breadcrumb) { ?>
			<li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
		<?php } ?>
	</ul>
	<div class="row">
		<div id="content" class="col-sm-12">
			<?php echo $content_top; ?>
			
			<style>
				:root {
				--accent-color: #0157c0;
				--site-color: #008fc3;
				}
				
				.pump-selector-page {
				margin: 0 auto;
				}
				
				.pump-selector-page h1 {
				margin-top: 0;
				font-size: 24px;
				font-weight: 600;
				}
				
				.pump-selector-intro {
				margin-bottom: 18px;
				}
				
				/* Summary */
				.pump-selector-summary,
				.pump-selector-consult-box,
				.pump-selector-consultation {
				margin-bottom: 24px;
				padding: 16px 18px;				
				/*background: #f6f8fb;
				border: 1px solid #e5e5e5;
				border-radius: 4px;*/
				background: #f6f8fb;
				border: 1px solid #dce6f2;
				border-radius: 10px;
				box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
				}
				.pump-selector-consult-box {
				margin-top: 28px;
				padding: 22px 24px;
				max-width: 880px
				/* border: 1px solid #dbe4ef;
				border-radius: 6px;
				background: #f8fbff; */
				}
				.pump-selector-after-results .alert.alert-warning ul{
				list-style: none;
				padding-left: 15px;
				}
				.pump-selector-summary-main {
				display: flex;
				flex-wrap: wrap;
				gap: 6px 0;
				align-items: center;
				font-size: 14px;
				line-height: 1.5;
				}
				
				.pump-selector-summary-main strong {
				margin-right: 6px;
				color: #111827;
				}
				
				.pump-selector-summary-main span {
				color: #111827;
				}
				
				.pump-selector-summary-main span:not(:last-child)::after {
				content: "·";
				margin: 0 8px;
				color: #98a2b3;
				}
				
				.pump-selector-summary-extra {
				margin-top: 4px;
				display: flex;
				flex-wrap: wrap;
				gap: 6px 0;
				font-size: 13px;
				line-height: 1.45;
				color: #667085;
				}
				
				.pump-selector-summary-extra span:not(:last-child)::after {
				content: "·";
				margin: 0 8px;
				color: #b0b7c3;
				}
				
				.pump-selector-summary-edit,
				#pump-selector-toggle-form {
				margin-top: 10px;
				padding: 7px 14px;
				border: 1px solid #cfd7e2;
				background: #fff;
				color: var(--accent-color);
				font-weight: 600;
				border-radius: 4px;
				}
				
				/* Form */
				.pump-selector-form-panel {
				margin-bottom: 28px;
				padding: 15px;
				background: #f6f8fb;
				border-radius: 4px;
				}
				
				.pump-selector-form-panel.collapsed {
				display: none;
				}
				
				.pump-selector-form-grid {
				display: flex;
				flex-wrap: nowrap;
				gap: 20px;
				}
				
				.pump-selector-form-section {
				flex: 1 0 32%;
				max-width: 32.5%;
				padding-left: 25px;
				padding-right: 25px;
				background: #fff;
				border: 1px solid #e7e7e7;
				border-radius: 15px;
				}
				
				.pump-selector-form-section h3 {
				margin-top: 0;
				margin-bottom: 14px;
				display: flex;
				align-items: center;
				justify-content: flex-start;
				font-size: 18px;
				}
				
				.pump-selector-form-section h3 .title-text {
				font-size: 17px !important;
				color: #000;
				font-weight: 600;
				}
				
				.pump-selector-form-section h3 .title-icon {
				padding-right: 15px;
				}
				
				.pump-selector-form-section .form-group {
				margin-left: 0;
				margin-right: 0;
				margin-bottom: 14px;
				}
				
				.pump-selector-form-section .control-label {
				display: block;
				margin-bottom: 5px;
				padding-top: 0;
				text-align: left;
				font-weight: 600;
				}
				
				.pump-selector-form-section input[type="text"],
				.pump-selector-form-section select.form-control {
				height: 38px;
				border-color: #cfd7e2;
				border-radius: 6px;
				}
				
				.pump-selector-form-section .checkbox,
				.pump-selector-form-section .radio-inline {
				margin-top: 0;
				}
				
				.pump-selector-form-section input[type="radio"]:checked,
				.pump-selector-form-section .radio-inline input[type="radio"]:checked {
				border-color: var(--accent-color);
				}
				
				.pump-selector-form-section input[type="radio"]:checked::after,
				.pump-selector-form-section .radio-inline input[type="radio"]:checked::after {
				background: var(--accent-color);
				}
				
				.pump-selector-form-section input[type="checkbox"]:checked::after,
				.pump-selector-form-section .checkbox input[type="checkbox"]:checked::after,
				.pump-selector-form-section .checkbox-inline input[type="checkbox"]:checked::after {
				border-right: 2px solid var(--accent-color);
				border-top: 2px solid var(--accent-color);
				}
				
				.pump-selector-page .form-control.is-disabled,
				.pump-selector-page input[disabled] {
				background: #f3f4f6;
				color: #98a2b3;
				cursor: not-allowed;
				}
				
				.pump-selector-help-box {
				margin-top: 12px;
				padding-top: 10px;
				border-top: 1px solid #e1e7ef;
				font-size: 13px;
				line-height: 1.35;
				}
				
				.pump-selector-help-box strong {
				display: inline;
				font-size: 13px;
				font-weight: 700;
				color: #111827;
				}
				
				.pump-selector-help-box div {
				display: inline;
				color: #475467;
				}
				
				.pump-selector-help-link {
				display: block;
				margin-top: 5px;
				font-size: 13px;
				font-weight: 700;
				color: var(--accent-color);
				}
				
				.pump-selector-actions {
				margin-top: 0;
				padding-top: 14px;
				text-align: right;
				}
				
				.btn-primary.btn-pump-selector-submit {
				padding: 10px 25px 10px 45px;
				background: var(--accent-color);
				border-radius: 6px;
				font-size: 18px;
				}
				
				.btn-primary.btn-pump-selector-submit .fa.fa-search,
				.btn-primary.btn-pump-selector-submit .fa.fa-spinner.fa-spin {
				position: relative;
				left: -15px;
				}
				
				/* Loading state: do not remove cursor: wait; user must see that calculation is running */
				.btn-pump-selector-submit.is-loading {
				opacity: 0.85;
				cursor: not-allowed;
				}
				
				/* .btn-pump-selector-submit[disabled] {
				pointer-events: none;
				} */
				
				.pump-selector-hp {
				position: absolute;
				left: -9999px;
				top: -9999px;
				opacity: 0;
				height: 0;
				overflow: hidden;
				}
				
				.pump-selector-js-errors {
				margin-bottom: 16px;
				padding: 12px 14px;
				border: 1px solid #f0c2c2;
				border-radius: 6px;
				background: #fff5f5;
				color: #9f1c1c;
				font-size: 14px;
				}
				
				.pump-selector-js-errors ul {
				margin: 0;
				padding-left: 18px;
				}
				
				/* Scheme */
				.pump-selector-scheme {
				margin: 18px 0 22px;
				border: 1px solid #dce6f2;
				border-radius: 8px;
				background: #f8fbff;
				overflow: hidden;
				}
				
				.pump-selector-scheme-toggle {
				width: 100%;
				padding: 14px 18px;
				border: 0;
				background: transparent;
				text-align: left;
				cursor: pointer;
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 16px;
				}
				
				.pump-selector-scheme-toggle:before {
				content: "?";
				flex: 0 0 30px;
				width: 30px;
				height: 30px;
				border-radius: 50%;
				background: var(--site-color);
				color: #fff;
				font-weight: 700;
				line-height: 30px;
				text-align: center;
				}
				
				.pump-selector-scheme-title {
				margin-right: auto;
				font-weight: 700;
				color: #111827;
				}
				
				.pump-selector-scheme-text {
				color: var(--site-color);
				font-weight: 600;
				white-space: nowrap;
				}
				
				.pump-selector-scheme-body {
				padding: 0 18px 18px;
				}
				
				.pump-selector-scheme-image {
				display: block;
				max-width: 100%;
				height: auto;
				border: 1px solid #e1e7ef;
				border-radius: 6px;
				background: #fff;
				}
				
				.pump-selector-scheme-close-wrap {
				margin-top: 12px;
				text-align: right;
				}
				
				.pump-selector-scheme-close {
				padding: 7px 14px;
				border: 1px solid #cfd7e2;
				background: #fff;
				color: var(--accent-color);
				border-radius: 4px;
				font-weight: 600;
				cursor: pointer;
				}
				
				.pump-selector-scheme-close:hover {
				background: #f1f6fd;
				}
				
				/* Results */
				.pump-selector-empty-result {
				margin-top: 24px;
				padding: 16px 20px;
				background: #fff;
				border: 1px dashed #cfd7e2;
				border-radius: 8px;
				color: #667085;
				}
				
				.pump-selector-results {
				margin-top: 20px;
				}
				
				.pump-selector-results-layout {
				position: relative;
				margin-top: 18px;
				}
				
				.pump-selector-results-layout > .col-lg-3 {
				padding-right: 18px;
				}
				
				.pump-selector-results-layout > .col-lg-9 {
				/* padding-left: 34px; */
				border-left: 0;
				}
				
				.pump-selector-results-item {
				margin-bottom: 30px;
				}
				
				.pump-selector-results .product-thumb {
				position: relative;
				min-height: 100%;
				}
				
				.pump-selector-badges {
				position: absolute;
				top: 10px;
				left: 10px;
				z-index: 2;
				}
				
				.pump-selector-badges .label {
				display: inline-block;
				margin: 0 4px 4px 0;
				padding: 6px 8px;
				font-size: 12px;
				}
				
				.pump-selector-specs {
				margin: 10px auto;
				padding: 10px;
				max-width: 260px;
				color: #fff;
				background: #17384d;
				border-radius: 4px;
				overflow: hidden;
				}
				
				.pump-selector-specs .spec-item {
				float: left;
				width: 50%;
				font-size: 12px;
				line-height: 1.3;
				}
				
				.pump-selector-specs .spec-value {
				display: block;
				font-size: 14px;
				font-weight: 700;
				}
				
				/* Result sidebar */
				.pump-selector-result-info {
				position: sticky;
				top: 15px;
				padding: 18px 16px;
				background: #f8fbff;
				border: 1px solid #dce6f2;
				border-radius: 10px;
				box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
				font-size: 14px;
				line-height: 1.45;
				}
				
				.pump-selector-result-info h3 {
				margin: 0 0 16px;
				padding-bottom: 10px;
				border-bottom: 1px solid #e1e7ef;
				font-size: 20px;
				line-height: 1.25;
				font-weight: 700;
				color: #111827;
				}
				
				.pump-selector-result-info-item {
				margin-bottom: 20px;
				}
				
				.pump-selector-result-info-item .label {
				display: inline-block;
				margin-bottom: 8px;
				padding: 5px 9px;
				font-size: 12px;
				font-weight: 700;
				border-radius: 4px;
				}
				
				.result-info-title {
				margin-bottom: 4px;
				font-size: 15px;
				line-height: 1.35;
				font-weight: 700;
				color: #1f2937;
				}
				
				.pump-selector-result-info-item p {
				margin: 0;
				font-size: 14px;
				line-height: 1.45;
				font-weight: 400;
				color: #475467;
				}
				
				.pump-selector-result-info-note {
				margin-top: 16px;
				padding: 12px;
				background: #fff;
				border: 1px solid #e1e7ef;
				border-radius: 6px;
				color: #475467;
				font-size: 13px;
				line-height: 1.45;
				}
				
				.pump-selector-result-info-note:before {
				content: "Важно";
				display: block;
				margin-bottom: 4px;
				font-weight: 700;
				color: #111827;
				}
				
				.pump-selector-after-results {
				margin-top: 24px;
				}
				
				.pump-selector-consultation {
				/* margin-top: 24px;
				padding: 20px;
				background: #f7f7f7;
				border: 1px solid #e5e5e5;
				border-radius: 4px; */
				}
				
				.pump-selector-consultation h3 {
				margin-top: 0;
				color: 
				}
				
				.pump-selector-consultation p {
				margin-bottom: 14px;
				}
				
				.pump-selector-product-thumb .image {
				position: relative;
				}
				
				.pump-selector-product-thumb .product-specs {
				/* margin: 10px 25px 0; */
				}
				
				.pump-selector-product-thumb .badge-flag {
				position: absolute;
				top: 25px;
				left: -20px;
				z-index: 3;
				}
				
				.pump-selector-product-thumb .badge-flag--green {
				background: #8BC34A;
				color: #000;
				}
				.pump-selector-product-thumb .badge-flag--green:after{
				border-left: 8px solid #8BC34A;
				}
				.pump-selector-product-thumb .badge-flag--blue {
				background: #6800bb;
				color: #fff;
				}
				.pump-selector-product-thumb .badge-flag--blue:after{
				border-left: 8px solid #6800bb;
				}
				
				.pump-selector-product-thumb .product_buttons .price {
				margin-bottom: 8px;
				}
				
				.pump-selector-product-thumb .product_buttons .number {
				float: left;
				}
				
				.pump-selector-product-thumb .product_buttons .cart,
				.pump-selector-product-thumb .product_buttons .compare {
				float: right;
				}
				.pump-selector-results-layout .product-thumb{
				overflow: visible
				}
				.pump-selector-product-thumb .product_buttons .cart a,
				.pump-selector-product-thumb .product_buttons .compare a {
				cursor: pointer;
				}
				.pump-selector-result-info {
				position: sticky;
				top: 15px;
				padding: 18px 16px;
				background: #f6f8fb;
				border: 1px solid #dce6f2;
				border-radius: 10px;
				box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
				font-size: 14px;
				line-height: 1.45;
				}
				
				.pump-selector-result-info h3 {
				margin: 0 0 14px;
				padding-bottom: 10px;
				border-bottom: 1px solid #e1e7ef;
				font-size: 20px;
				line-height: 1.25;
				font-weight: 700;
				color: #008fc3;
				}
				
				.pump-selector-info-card {
				margin-bottom: 12px;
				padding: 11px 12px;
				background: #fff;
				border: 1px solid #e1e7ef;
				border-left-width: 4px;
				border-radius: 7px;
				}
				
				.pump-selector-info-card--best {
				border-left-color: #8BC34A;
				}
				
				.pump-selector-info-card--optimal {
				border-left-color: #F3C536;
				}
				
				.pump-selector-info-card--premium {
				border-left-color: #6800bb;
				}
				
				.pump-selector-info-card-head {
				display: flex;
				align-items: center;
				gap: 7px;
				margin-bottom: 6px;
				}
				
				.pump-selector-info-dot {
				width: 9px;
				height: 9px;
				border-radius: 50%;
				display: inline-block;
				flex: 0 0 9px;
				}
				
				.pump-selector-info-card--best .pump-selector-info-dot {
				background: #8BC34A;
				}
				
				.pump-selector-info-card--optimal .pump-selector-info-dot {
				background: #F3C536;
				}
				
				.pump-selector-info-card--premium .pump-selector-info-dot {
				background: #6800bb;
				}
				
				.pump-selector-info-label {
				font-size: 12px;
				line-height: 1.2;
				font-weight: 700;
				color: #667085;
				text-transform: uppercase;
				letter-spacing: .02em;
				}
				
				.pump-selector-info-title {
				margin-bottom: 4px;
				font-size: 15px;
				line-height: 1.35;
				font-weight: 700;
				color: #1f2937;
				}
				h3.h3_no_blue{
				color:#111827!important;
				}
				.pump-selector-info-card p {
				margin: 0;
				font-size: 13px;
				line-height: 1.45;
				color: #475467;
				}
				
				.pump-selector-result-info-note {
				margin-top: 14px;
				padding: 12px;
				background: #fff;
				border: 1px solid #e1e7ef;
				border-radius: 7px;
				color: #475467;
				font-size: 13px;
				line-height: 1.45;
				}
				
				.pump-selector-result-info-note:before {
				content: "Важно";
				display: block;
				margin-bottom: 4px;
				font-weight: 700;
				color: #111827;
				}
				.pump-selector-product-thumb .caption h4 {
				min-height: 40px;
				}
				.pump-selector-info-card--best .pump-selector-info-icon {
				background: #8BC34A;
				color: #111;
				}
				
				.pump-selector-info-card--optimal .pump-selector-info-icon {
				background: #F3C536;
				color: #111;
				
				}
				.pump-selector-info-card--optimal .pump-selector-info-icon .fa.fa-balance-scale:before{
				display: block;
				max-width: 16px;
				}
				
				.pump-selector-info-card--premium .pump-selector-info-icon {
				background: #6800bb;
				color: #fff;
				}
				.pump-selector-info-icon{
				width: 32px;
				height: 24px;
				display: flex;
				align-items: center;
				justify-content: center;
				border-radius: 50%;
				}
				.pump-selector-scheme-text {
				text-decoration: underline;
				text-decoration-style: dashed;
				transition: .2s;
				}
				.pump-selector-scheme-text:hover{
				color: var(--accent-color);
				}
				.pump-selector-cta-card {
				min-height: 278px;
				padding: 28px 24px;
				border: 1px dashed #c8d6e5;
				background: #f8fbff;
				text-align: center;
				display: flex;
				flex-direction: column;
				justify-content: center;
				}
				
				.pump-selector-cta-icon {
				width: 46px;
				height: 46px;
				margin: 0 auto 16px;
				border-radius: 50%;
				background: #eef5ff;
				color: #0057b8;
				font-size: 24px;
				font-weight: 700;
				line-height: 46px;
				}
				
				.pump-selector-cta-title {
				margin-bottom: 10px;
				font-size: 17px;
				font-weight: 700;
				color: #1f2937;
				}
				
				.pump-selector-cta-text {
				margin-bottom: 18px;
				font-size: 13px;
				line-height: 1.45;
				color: #4b5563;
				}
				
				.pump-selector-cta-button {
				align-self: center;
				}
				
				
				.pump-selector-consult-box h3 {
				margin-top: 0;
				margin-bottom: 12px;
				font-size: 20px;
				}
				
				.pump-selector-consult-box p {
				margin-bottom: 16px;
				max-width: 860px;
				}
				.pump-selector-warnings ul {
				padding-left: 0;
				margin-bottom: 0;
				list-style: none;
				}
				
				.pump-selector-warnings li {
				margin-bottom: 0;
				}
				.pump-selector-warnings {
				padding: 14px 18px;
				background: #fff8df;
				border-left: 4px solid #f0b429;
				color: #7a5200;
				}
				.pump-selector-calc-table {
				width: 100%;
				border-collapse: collapse;
				background: #fff;
				}
				
				.pump-selector-calc-table td {
				padding: 10px 12px;
				border: 1px solid #e1e5ea;
				}
				
				.pump-selector-calc-table td:first-child {
				width: 70%;
				color: #4b5563;
				}
				
				.pump-selector-calc-table td:last-child {
				font-weight: 600;
				}
				
				@media (max-width: 991px) {
				.pump-selector-form-grid {
				flex-wrap: wrap;
				}
				
				.pump-selector-form-section {
				flex: 1 0 48%;
				max-width: 48%;
				}
				
				.pump-selector-results-layout > .col-lg-3 {
				padding-right: 15px;
				}
				
				.pump-selector-results-layout > .col-lg-9 {
				padding-left: 15px;
				}
				
				.pump-selector-result-info {
				position: static;
				margin-bottom: 20px;
				}
				}
				
				@media (max-width: 767px) {
				.pump-selector-form-grid {
				display: block;
				}
				
				.pump-selector-form-section {
				width: 100%;
				max-width: none;
				margin-bottom: 16px;
				}
				
				.pump-selector-actions {
				text-align: left;
				}
				
				.pump-selector-scheme-toggle {
				align-items: flex-start;
				flex-direction: column;
				padding-left: 54px;
				position: relative;
				}
				
				.pump-selector-scheme-toggle:before {
				position: absolute;
				left: 16px;
				top: 14px;
				}
				
				.pump-selector-scheme-text {
				white-space: normal;
				}
				}
			</style>
			
			<div class="pump-selector-page">
				<h1><?php echo $heading_title; ?></h1>
				
				<p class="pump-selector-intro">Это предварительный подбор. Окончательный выбор насоса требует подтверждения специалистом.</p>
				
				
				
				<?php if ($requirements) { ?>
					<div class="pump-selector-summary">
						<div class="pump-selector-summary-main">
							<strong>Ваш расчет:</strong>
							
							<span>Напор: <?php echo $requirements['required_head_m']; ?> м</span>
							<span>Расход: <?php echo $requirements['required_flow_l_min']; ?> л/мин</span>
							<span>Напряжение: <?php echo $requirements['selected_voltage']; ?>В</span>
							<span>Диаметр: <?php echo ($requirements['casing_diameter_mm'] !== null) ? $requirements['casing_diameter_mm'] . ' мм' : 'не указан'; ?></span>
						</div>
						
						<div class="pump-selector-summary-extra">
							<span>
								Глубина: <?php echo htmlspecialchars($input['total_well_depth_m'], ENT_QUOTES, 'UTF-8'); ?> м
							</span>
							
							<span>
								Уровень воды:
								<?php if ($input['water_level_mode'] == 'known') { ?>
									<?php echo htmlspecialchars($input['water_level_m'], ENT_QUOTES, 'UTF-8'); ?> м
									<?php } else { ?>
									не указан
								<?php } ?>
							</span>
							
							<span>
								До дома: <?php echo htmlspecialchars($input['distance_to_house_m'], ENT_QUOTES, 'UTF-8'); ?> м
							</span>
							
							<span>
								Верхняя точка воды:
								<?php if ($input['highest_water_point_floor'] == '3') { ?>
									3 этажа и выше
									<?php if (!empty($input['custom_vertical_lift_m'])) { ?>
										(<?php echo htmlspecialchars($input['custom_vertical_lift_m'], ENT_QUOTES, 'UTF-8'); ?> м)
									<?php } ?>
									<?php } elseif ($input['highest_water_point_floor'] == '2') { ?>
									2 этажа
									<?php } else { ?>
									1 этаж
								<?php } ?>
							</span>
						</div>
						
						<button type="button" class="btn btn-default pump-selector-summary-edit" id="pump-selector-toggle-form">
							Изменить параметры
						</button>
					</div>
				<?php } ?>
				
				<noscript>
					<style>
						.pump-selector-form-panel.collapsed {
						display: block;
						}
					</style>
				</noscript>
				
				<div class="pump-selector-form-panel<?php if ($requirements) { ?> collapsed<?php } ?>" id="pump-selector-form-panel">
					<div class="pump-selector-scheme">
						<button type="button" class="pump-selector-scheme-toggle" id="pump-selector-scheme-toggle">
							<span class="pump-selector-scheme-title">Не знаете, что означают параметры скважины?</span>
							<span class="pump-selector-scheme-text">Показать схему: глубина, уровень воды, расстояние до дома</span>
						</button>
						
						<div class="pump-selector-scheme-body" id="pump-selector-scheme-body" style="display: none;">
							<img
							src="catalog/view/theme/revolution/image/pump-selector/well-scheme.png"
							alt="Схема скважины: глубина, уровень воды, расстояние до дома"
							class="img-responsive pump-selector-scheme-image"
							/>
							<div class="pump-selector-scheme-close-wrap">
								<button type="button" class="pump-selector-scheme-close" id="pump-selector-scheme-close">
									Скрыть схему
								</button>
							</div>
						</div>
					</div>
					<?php if ($errors) { ?>
						<div class="alert alert-danger">
							<ul>
								<?php foreach ($errors as $error) { ?>
									<li><?php echo $error; ?></li>
								<?php } ?>
							</ul>
						</div>
					<?php } ?>
					
					<form action="<?php echo $action; ?>" method="post">
						<div class="pump-selector-js-errors" id="pump-selector-js-errors" style="display: none;"></div>
						<div class="pump-selector-hp">
							<label>Не заполняйте это поле</label>
							<input type="text" name="website" value="" autocomplete="off" tabindex="-1" />
						</div>
						<div class="pump-selector-form-grid">
							<div class="pump-selector-form-section">
								<h3><span class="title-icon"><img class="title-icon-image" src="/catalog/view/theme/revolution/image/pump.webp" alt="title icon"></span><span class="title-text">Данные скважины</span></h3>
								
								<div class="form-group">
									<label class="control-label " for="input-total-well-depth">Глубина скважины, м</label>
									<input type="text" name="total_well_depth_m" value="<?php echo htmlspecialchars($input['total_well_depth_m'], ENT_QUOTES, 'UTF-8'); ?>" id="input-total-well-depth" class="form-control  pump-selector-number" />
								</div>
								
								<div class="form-group">
									<label class="control-label">Уровень воды</label>
									<label class="radio-inline">
										<input type="radio" name="water_level_mode" value="known"<?php if ($input['water_level_mode'] == 'known') { ?> checked="checked"<?php } ?> /> Известен
									</label>
									<label class="radio-inline">
										<input type="radio" name="water_level_mode" value="unknown"<?php if ($input['water_level_mode'] == 'unknown') { ?> checked="checked"<?php } ?> /> Не знаю
									</label>
								</div>
								
								<div class="form-group">
									<label class="control-label" for="input-water-level">Уровень воды, м</label>
									<input type="text" name="water_level_m" value="<?php echo htmlspecialchars($input['water_level_m'], ENT_QUOTES, 'UTF-8'); ?>" id="input-water-level" class="form-control  pump-selector-number" />
								</div>
								
								<div class="form-group">
									<label class="control-label" for="input-distance">Расстояние до дома, м</label>
									<input type="text" name="distance_to_house_m" value="<?php echo htmlspecialchars($input['distance_to_house_m'], ENT_QUOTES, 'UTF-8'); ?>" id="input-distance" class="form-control  pump-selector-number" />
								</div>
							</div>
							
							<div class="pump-selector-form-section">
								<h3><span class="title-icon"><img class="title-icon-image" src="/catalog/view/theme/revolution/image/home-water.webp" alt="title icon"></span><span class="title-text">Дом и расход воды</span></h3>
								
								<div class="form-group">
									<label class="control-label" for="input-floor">Самая высокая точка</label>
									<select name="highest_water_point_floor" id="input-floor" class="form-control">
										<option value="1"<?php if ($input['highest_water_point_floor'] == '1') { ?> selected="selected"<?php } ?>>1 этаж</option>
										<option value="2"<?php if ($input['highest_water_point_floor'] == '2') { ?> selected="selected"<?php } ?>>2 этаж</option>
										<option value="3"<?php if ($input['highest_water_point_floor'] == '3') { ?> selected="selected"<?php } ?>>3 этажа и выше</option>
									</select>
								</div>
								
								<div class="form-group">
									<label class="control-label" for="input-custom-lift">Высота для 3+ этажей, м</label>
									<input type="text" name="custom_vertical_lift_m" value="<?php echo htmlspecialchars($input['custom_vertical_lift_m'], ENT_QUOTES, 'UTF-8'); ?>" id="input-custom-lift" class="form-control pump-selector-number" />
									<div class="help-block">Можно оставить пустым — будет принято 9 м.</div>
								</div>
								
								<div class="form-group">
									<label class="control-label">Точки разбора воды (выберите все, что есть)</label>
									
									<div class="row">
										<div class="col-xs-5">
											<?php $i = 0; ?>
											<?php foreach ($water_point_labels as $key => $label) { ?>
												<?php if ($i < 3) { ?>
													<div class="checkbox">
														<label>
															<input type="checkbox" name="water_points[]" value="<?php echo $key; ?>"<?php if (is_array($input['water_points']) && in_array($key, $input['water_points'])) { ?> checked="checked"<?php } ?> />
															<?php echo $label; ?>
														</label>
													</div>
												<?php } ?>
												<?php $i++; ?>
											<?php } ?>
										</div>
										
										<div class="col-xs-7">
											<?php $i = 0; ?>
											<?php foreach ($water_point_labels as $key => $label) { ?>
												<?php if ($i >= 3) { ?>
													<div class="checkbox">
														<label>
															<input type="checkbox" name="water_points[]" value="<?php echo $key; ?>"<?php if (is_array($input['water_points']) && in_array($key, $input['water_points'])) { ?> checked="checked"<?php } ?> />
															<?php echo $label; ?>
														</label>
													</div>
												<?php } ?>
												<?php $i++; ?>
											<?php } ?>
										</div>
									</div>
								</div>
							</div>
							
							<div class="pump-selector-form-section">
								<h3><span class="title-icon"><img class="title-icon-image" src="/catalog/view/theme/revolution/image/plig-ins.webp" alt="title icon"></span><span class="title-text">Совместимость</span></h3>
								
								<div class="form-group">
									<label class="control-label">Диаметр обсадной трубы</label>
									<label class="radio-inline">
										<input type="radio" name="casing_diameter_mode" value="known"<?php if ($input['casing_diameter_mode'] == 'known') { ?> checked="checked"<?php } ?> /> Известен
									</label>
									<label class="radio-inline">
										<input type="radio" name="casing_diameter_mode" value="unknown"<?php if ($input['casing_diameter_mode'] == 'unknown') { ?> checked="checked"<?php } ?> /> Не знаю
									</label>
								</div>
								
								<div class="form-group">
									<label class="control-label" for="input-casing-diameter">Диаметр, мм</label>
									<input type="text" name="casing_diameter_mm" value="<?php echo htmlspecialchars($input['casing_diameter_mm'], ENT_QUOTES, 'UTF-8'); ?>" id="input-casing-diameter" class="form-control pump-selector-number" />
								</div>
								
								<div class="form-group">
									<label class="control-label">Напряжение</label>
									<label class="radio-inline">
										<input type="radio" name="voltage_mode" value="220"<?php if ($input['voltage_mode'] == '220') { ?> checked="checked"<?php } ?> /> 220В
									</label>
									<label class="radio-inline">
										<input type="radio" name="voltage_mode" value="380"<?php if ($input['voltage_mode'] == '380') { ?> checked="checked"<?php } ?> /> 380В
									</label>
									<label class="radio-inline">
										<input type="radio" name="voltage_mode" value="unknown"<?php if ($input['voltage_mode'] == 'unknown') { ?> checked="checked"<?php } ?> /> Не знаю
									</label>
								</div>
								<div class="pump-selector-help-box">
									<strong>Не знаете параметры?</strong>
									<div>Поможем уточнить диаметр, напряжение и подходящий насос.</div>
									<a class="pump-selector-help-link" href="index.php?route=information/contact">Получить консультацию</a>
								</div>
							</div>
						</div>
						
						<div class="pump-selector-actions">
							<button type="submit" class="btn btn-primary btn-pump-selector-submit" id="pump-selector-submit">
								<span class="pump-selector-submit-text">
									<i class="fa fa-search"></i> Подобрать насос
								</span>
								<span class="pump-selector-submit-loading" style="display: none;">
									<i class="fa fa-spinner fa-spin"></i> Подбираю...
								</span>
							</button>
						</div>
						
					</form>
				</div>
				
				<?php if ($requirements) { ?>
					<?php if ($products) { ?>
						<div class="pump-selector-results">
							<h2>Подходящие варианты</h2>
							
							<div class="pump-selector-results-layout row">
								
								<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
									<div class="pump-selector-result-info">
										<h3 class="h3_no_blue">Как читать подбор</h3>
										
										<div class="pump-selector-info-card pump-selector-info-card--best">
											<div class="pump-selector-info-card-head">
												<span class="pump-selector-info-icon">
													<i class="fa fa-rub"></i>
												</span>
												<span class="pump-selector-info-label">Лучшая цена</span>
											</div>
											<div class="pump-selector-info-title">Минимальная цена</div>
											<p>Подходящий насос без лишнего запаса.</p>
										</div>
										
										<div class="pump-selector-info-card pump-selector-info-card--optimal">
											<div class="pump-selector-info-card-head">
												<span class="pump-selector-info-icon">
													<i class="fa fa-balance-scale"></i>
												</span>
												<span class="pump-selector-info-label">Оптимальный выбор</span>
											</div>
											<div class="pump-selector-info-title">Баланс цены и запаса</div>
											<p>Обычно основной вариант для дома.</p>
										</div>
										<?php $has_premium = !empty($products) && count($products) >= 3; ?>
										<?php if($has_premium){ ?>
											<div class="pump-selector-info-card pump-selector-info-card--premium">
												<div class="pump-selector-info-card-head">
													<span class="pump-selector-info-icon">
														<i class="fa fa-diamond"></i>
													</span>
													<span class="pump-selector-info-label">Премиум качество</span>
												</div>
												<div class="pump-selector-info-title">Запас по напору и брендовое качество</div>
												<p>Для надежности, ресурса и спокойного выбора.</p>
											</div>
										<?php } ?>
										<div class="pump-selector-result-info-note">
											Подбор предварительный. Перед покупкой лучше подтвердить параметры скважины со специалистом.
										</div>
									</div>
								</div>
								
								<div class="col-lg-9 col-md-9 col-sm-12 col-xs-12">
									<div class="row">
										<?php $result_count = !empty($products) ? count($products) : 0; ?>
										
										<?php foreach ($products as $product) { ?>
											<div class="product-layout product-grid col-lg-4 col-md-4 col-sm-6 col-xs-12 pump-selector-results-item">
												<?php
													$flow_m3_h = round($product['max_flow_l_min'] * 0.06, 1);
												?>
												
												<div class="product-thumb product_<?php echo $product['product_id']; ?> pump-selector-product-thumb" itemprop="itemListElement" itemscope itemtype="http://schema.org/Product">
													
													<div class="image pb-60">
														<a href="<?php echo $product['href']; ?>">
															<img
															src="<?php echo $product['thumb']; ?>"
															alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
															title="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
															class="img-responsive"
															itemprop="image"
															/>
														</a>
														
														<div class="stiker_panel"></div>
														
														<div class="fapanel rev_wish_srav_prod">
															<div class="lupa">
																<a onclick="get_revpopup_view('<?php echo $product['product_id']; ?>');">
																	<i data-toggle="tooltip" data-placement="left" title="Быстрый просмотр" class="fa fa-border fa-eye"></i>
																</a>
															</div>
															
															<div class="zakaz">
																<a onclick="get_revpopup_purchase('<?php echo $product['product_id']; ?>');">
																	<i data-toggle="tooltip" data-placement="left" title="Купить в 1 клик" class="fa fa-border fa-gavel"></i>
																</a>
															</div>
														</div>
														
														<?php foreach ($product['result_types'] as $result_type) { ?>
															<?php if ($result_type == 'best_price') { ?>
																<div class="badge-flag badge-flag--green">
																	<span class="star">★</span>
																	<?php echo isset($result_type_labels[$result_type]) ? $result_type_labels[$result_type] : 'Лучшая цена'; ?>
																</div>
																<?php } elseif ($result_type == 'optimal_choice') { ?>
																<div class="badge-flag badge-flag--yellow">
																	<span class="star">★</span>
																	<?php echo isset($result_type_labels[$result_type]) ? $result_type_labels[$result_type] : 'Оптимальный выбор'; ?>
																</div>
																<?php } elseif ($result_type == 'premium') { ?>
																<div class="badge-flag badge-flag--blue">
																	<span class="star">★</span>
																	<?php echo isset($result_type_labels[$result_type]) ? $result_type_labels[$result_type] : 'Премиум качество'; ?>
																</div>
															<?php } ?>
														<?php } ?>
														
														<div class="product-specs">
															<div class="spec-item">
																<img src="catalog/view/theme/revolution/image/spec/dropwater.svg" width="30" height="30" alt="" />
																<div class="spec-text">
																	<div class="label">Напор</div>
																	<div class="value">До <?php echo $product['max_head_m']; ?> м</div>
																</div>
															</div>
															
															<div class="spec-item-divider" style="width:1px;height:30px;background: #fff;"></div>
															
															<div class="spec-item">
																<img src="catalog/view/theme/revolution/image/spec/seawaves.svg" width="30" height="30" alt="" />
																<div class="spec-text">
																	<div class="label">Подача</div>
																	<div class="value"><?php echo $flow_m3_h; ?> м³/ч</div>
																</div>
															</div>
														</div>
													</div>
													
													<div class="caption product-info clearfix" style="margin-left: initial;">
														<h4>
															<a href="<?php echo $product['href']; ?>">
																<span itemprop="name">
																	<?php echo $product['name'] ? $product['name'] : 'Товар #' . $product['product_id']; ?>
																</span>
															</a>
														</h4>
														
														<link itemprop="url" href="<?php echo $product['href']; ?>" />
														
														<div class="rating">
															<span itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
																<meta itemprop="worstRating" content="1" />
																<meta itemprop="bestRating" content="5" />
																<meta itemprop="ratingValue" content="0.0" />
																<meta itemprop="reviewCount" content="" />
																<span class="fa fa-stack"><i class="fa fa-star-o fa-stack-2x"></i></span>
																<span class="fa fa-stack"><i class="fa fa-star-o fa-stack-2x"></i></span>
																<span class="fa fa-stack"><i class="fa fa-star-o fa-stack-2x"></i></span>
																<span class="fa fa-stack"><i class="fa fa-star-o fa-stack-2x"></i></span>
																<span class="fa fa-stack"><i class="fa fa-star-o fa-stack-2x"></i></span>
															</span>
														</div>
														
														<div class="description_options">
															<div class="description">
																<?php if ($product['manufacturer']) { ?>
																	<span class="attr_i_1">
																		<span class="span_attr_name">Производитель:</span>
																		<?php echo $product['manufacturer']; ?>
																	</span>
																<?php } ?>
															</div>
														</div>
														
														<div class="product_buttons">
															<div class="fapanel-price">
																<div class="zakaz">
																	<a onclick="get_revpopup_purchase('<?php echo $product['product_id']; ?>');">
																		<i data-toggle="tooltip" data-placement="top" title="Купить в 1 клик" class="fa fa-border fa-gavel"></i>
																	</a>
																</div>
																
																<div class="lupa">
																	<a onclick="get_revpopup_view('<?php echo $product['product_id']; ?>');">
																		<i data-toggle="tooltip" data-placement="top" title="Быстрый просмотр" class="fa fa-border fa-eye"></i>
																	</a>
																</div>
															</div>
															
															<?php if ($product['price']) { ?>
																<div class="price" itemprop="offers" itemscope itemtype="http://schema.org/Offer">
																	<?php if (!$product['special']) { ?>
																		<span class="price_no_format"><?php echo $product['price']; ?></span>
																		<?php } else { ?>
																		<span class="price-new"><?php echo $product['special']; ?></span>
																		<span class="price-old"><?php echo $product['price']; ?></span>
																	<?php } ?>
																	
																	<meta itemprop="priceCurrency" content="RUB" />
																	
																	<?php if ($product['tax']) { ?>
																		<small class="price-hint price-hint--top-1">с НДС</small>
																	<?php } ?>
																</div>
															<?php } ?>
															
															<div class="number">
																<div class="frame-change-count">
																	<div class="btn-plus">
																		<button type="button" onclick="var q=document.getElementById('pump-selector-qty-<?php echo $product['product_id']; ?>'); q.value=parseInt(q.value || 1, 10)+1;">+</button>
																	</div>
																	
																	<div class="btn-minus">
																		<button type="button" onclick="var q=document.getElementById('pump-selector-qty-<?php echo $product['product_id']; ?>'); q.value=Math.max(1, parseInt(q.value || 1, 10)-1);">-</button>
																	</div>
																</div>
																
																<input
																type="text"
																name="quantity"
																id="pump-selector-qty-<?php echo $product['product_id']; ?>"
																class="plus-minus"
																value="<?php echo $product['minimum']; ?>"
																/>
															</div>
															
															<div class="clearfix"></div>
															
															<div class="compare">
																<a onclick="compare.add('<?php echo $product['product_id']; ?>', '');" data-toggle="tooltip" title="Сравнить">
																	<i class="fa fa-border fa-bar-chart-o"></i>
																</a>
															</div>
															
															<div class="cart">
																<a onclick="cart.add('<?php echo $product['product_id']; ?>', document.getElementById('pump-selector-qty-<?php echo $product['product_id']; ?>').value);" data-toggle="tooltip" title="Купить">
																	<i class="fa fa-border fa-shopping-basket">
																		<span class="prlistb">Купить</span>
																	</i>
																</a>
															</div>
														</div>
													</div>
												</div>
											</div>
										<?php } ?>
										<?php if ($result_count == 2) { ?>
											<div class="product-layout col-lg-4 col-md-4 col-sm-6 col-xs-12">
												<div class="pump-selector-cta-card">
													<div class="pump-selector-cta-icon">?</div>
													
													<div class="pump-selector-cta-title">
														Нужен вариант с большим запасом?
													</div>
													
													<div class="pump-selector-cta-text">
														Если хотите насос с повышенным запасом по напору и ресурсу — специалист проверит параметры и подберёт подходящую модель.
													</div>
													
													<button type="button" class="btn btn-primary pump-selector-cta-button">
														Получить консультацию
													</button>
												</div>
											</div>
										<?php } ?>
									</div>
								</div>
								
							</div>
						</div>
						
						<?php if ($result_count != 2) { ?>
							<div class="pump-selector-consult-box">
								<h3 class="h3_no_blue">Не уверены в выборе?</h3>
								<p>Специалист проверит параметры скважины, уровень воды, диаметр обсадной трубы и условия установки перед покупкой.</p>
								<button type="button" class="btn btn-primary">
									Получить консультацию специалиста
								</button>
							</div>
						<?php } ?>
						
						<?php } else { ?>
						<div class="alert alert-info">
							По введенным условиям не найдено подходящих насосов для предварительного подбора.
							Проверьте введенные данные или обратитесь к специалисту.
						</div>
					<?php } ?>
					
					<div class="pump-selector-after-results">
						<?php if ($warnings) { ?>
							<h3 class="h3_no_blue">Важно перед покупкой</h3>
							
							<div class="alert alert-warning">
								<ul>
									<?php foreach ($warnings as $warning) { ?>
										<li><?php echo $warning; ?></li>
									<?php } ?>
								</ul>
							</div>
						<?php } ?>
						
						<h3 class="h3_no_blue">Расчетные параметры</h3>
						
						<table class="table table-bordered">
							<tbody>
								<tr>
									<td>Требуемый напор</td>
									<td><?php echo $requirements['required_head_m']; ?> м</td>
								</tr>
								
								<tr>
									<td>Требуемая подача</td>
									<td><?php echo $requirements['required_flow_l_min']; ?> л/мин</td>
								</tr>
								
								<tr>
									<td>Напряжение</td>
									<td>
										<?php echo $requirements['selected_voltage']; ?>В
										<?php if ($requirements['voltage_was_assumed']) { ?>, принято по умолчанию<?php } ?>
									</td>
								</tr>
								
								<tr>
									<td>Диаметр обсадной трубы</td>
									<td>
										<?php echo ($requirements['casing_diameter_mm'] !== null) ? $requirements['casing_diameter_mm'] . ' мм' : 'не указан'; ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					
					<?php } else { ?>
					<div class="pump-selector-empty-result">
						Заполните данные скважины, чтобы получить предварительный подбор насоса.
					</div>
				<?php } ?>
			</div>
			
			<script type="text/javascript">
				(function() {
					var button = document.getElementById('pump-selector-toggle-form');
					var panel = document.getElementById('pump-selector-form-panel');
					
					if (button && panel) {
						button.onclick = function() {
							if (panel.className.indexOf('collapsed') !== -1) {
								panel.className = panel.className.replace(/\s*collapsed/g, '');
								button.innerHTML = 'Скрыть параметры';
								} else {
								panel.className += ' collapsed';
								button.innerHTML = 'Изменить параметры';
							}
						};
					}
				})();
			</script>
			
			<script type="text/javascript">
				document.addEventListener('DOMContentLoaded', function () {
					var toggle = document.getElementById('pump-selector-scheme-toggle');
					var closeBtn = document.getElementById('pump-selector-scheme-close');
					var body = document.getElementById('pump-selector-scheme-body');
					
					if (!toggle || !body) {
						return;
					}
					
					function openScheme() {
						body.style.display = 'block';
					}
					
					function closeScheme() {
						body.style.display = 'none';
					}
					
					toggle.addEventListener('click', function () {
						if (body.style.display === 'none' || body.style.display === '') {
							openScheme();
							} else {
							closeScheme();
						}
					});
					
					if (closeBtn) {
						closeBtn.addEventListener('click', function () {
							closeScheme();
							
							if (toggle.scrollIntoView) {
								toggle.scrollIntoView({
									behavior: 'smooth',
									block: 'center'
								});
							}
						});
					}
				});
			</script>
			<script type="text/javascript">
				document.addEventListener('DOMContentLoaded', function () {
					var form = document.getElementById('pump-selector-form');
					
					if (!form) {
						form = document.querySelector('.pump-selector-page form');
					}
					
					var submit = document.getElementById('pump-selector-submit');
					var errorsBox = document.getElementById('pump-selector-js-errors');
					
					if (!form) {
						return;
					}
					
					function getField(name) {
						return form.querySelector('[name="' + name + '"]');
					}
					
					function getCheckedValue(name) {
						var checked = form.querySelector('[name="' + name + '"]:checked');
						return checked ? checked.value : '';
					}
					
					function getCheckedCount(name) {
						return form.querySelectorAll('[name="' + name + '"]:checked').length;
					}
					
					function isEmpty(field) {
						return !field || !field.value || field.value.replace(/\s+/g, '') === '';
					}
					
					function isPositiveNumber(field) {
						if (isEmpty(field)) {
							return false;
						}
						
						var value = field.value.replace(',', '.');
						var number = parseFloat(value);
						
						return !isNaN(number) && number > 0;
					}
					
					function showErrors(errors) {
						if (!errorsBox) {
							alert(errors.join("\n"));
							return;
						}
						
						var html = '<ul>';
						
						for (var i = 0; i < errors.length; i++) {
							html += '<li>' + errors[i] + '</li>';
						}
						
						html += '</ul>';
						
						errorsBox.innerHTML = html;
						errorsBox.style.display = 'block';
						
						if (errorsBox.scrollIntoView) {
							errorsBox.scrollIntoView({
								behavior: 'smooth',
								block: 'center'
							});
						}
					}
					
					function clearErrors() {
						if (errorsBox) {
							errorsBox.innerHTML = '';
							errorsBox.style.display = 'none';
						}
					}
					
					function startLoading() {
						if (!submit) {
							return;
						}
						
						var text = submit.querySelector('.pump-selector-submit-text');
						var loading = submit.querySelector('.pump-selector-submit-loading');
						
						submit.disabled = true;
						submit.classList.add('is-loading');
						
						if (text) {
							text.style.display = 'none';
						}
						
						if (loading) {
							loading.style.display = 'inline-block';
						}
					}
					
					form.addEventListener('submit', function (event) {
						var errors = [];
						
						clearErrors();
						
						var totalWellDepth = getField('total_well_depth_m');
						var distanceToHouse = getField('distance_to_house_m');
						var waterLevelMode = getCheckedValue('water_level_mode');
						var waterLevel = getField('water_level_m');
						var casingDiameterMode = getCheckedValue('casing_diameter_mode');
						var casingDiameter = getField('casing_diameter_mm');
						
						if (!isPositiveNumber(totalWellDepth)) {
							errors.push('Укажите глубину скважины.');
						}
						
						if (waterLevelMode === 'known' && !isPositiveNumber(waterLevel)) {
							errors.push('Укажите уровень воды или выберите “Не знаю”.');
						}
						
						if (!isPositiveNumber(distanceToHouse)) {
							errors.push('Укажите расстояние до дома.');
						}
						
						if (getCheckedCount('water_points[]') === 0) {
							errors.push('Выберите хотя бы одну точку расхода воды.');
						}
						
						if (casingDiameterMode === 'known' && !isPositiveNumber(casingDiameter)) {
							errors.push('Укажите диаметр обсадной трубы или выберите “Не знаю”.');
						}
						
						if (errors.length > 0) {
							event.preventDefault();
							showErrors(errors);
							return false;
						}
						
						startLoading();
					});
				});
			</script>
			<script type="text/javascript">
				document.addEventListener('DOMContentLoaded', function () {
					function toggleKnownField(radioName, knownValue, inputName) {
						var radios = document.querySelectorAll('input[name="' + radioName + '"]');
						var input = document.querySelector('input[name="' + inputName + '"]');
						
						if (!radios.length || !input) {
							return;
						}
						
						function update() {
							var checked = document.querySelector('input[name="' + radioName + '"]:checked');
							
							if (!checked) {
								return;
							}
							
							if (checked.value === knownValue) {
								input.disabled = false;
								input.classList.remove('is-disabled');
								} else {
								input.value = '';
								input.disabled = true;
								input.classList.add('is-disabled');
							}
						}
						
						for (var i = 0; i < radios.length; i++) {
							radios[i].addEventListener('change', update);
						}
						
						update();
					}
					
					toggleKnownField('water_level_mode', 'known', 'water_level_m');
					toggleKnownField('casing_diameter_mode', 'known', 'casing_diameter_mm');
				});
			</script>
			<script type="text/javascript">
				document.addEventListener('DOMContentLoaded', function () {
					var numberFields = document.querySelectorAll('.pump-selector-number');
					
					for (var i = 0; i < numberFields.length; i++) {
						numberFields[i].addEventListener('input', function () {
							var value = this.value;
							
							// Оставляем только цифры, точку и запятую
							value = value.replace(/[^0-9.,]/g, '');
							
							// Оставляем только один десятичный разделитель
							var firstSeparator = value.search(/[.,]/);
							
							if (firstSeparator !== -1) {
								var before = value.substring(0, firstSeparator + 1);
								var after = value.substring(firstSeparator + 1).replace(/[.,]/g, '');
								value = before + after;
							}
							
							this.value = value;
						});
					}
				});
			</script>
			<script type="text/javascript">
				document.addEventListener('DOMContentLoaded', function () {
					var floor = document.getElementById('input-floor');
					var lift = document.getElementById('input-custom-lift');

					if (!floor || !lift) {
						return;
					}

					function updateLiftField() {
						if (floor.value === '3') {
							lift.disabled = false;
							lift.classList.remove('is-disabled');
						} else {
							lift.value = '';
							lift.disabled = true;
							lift.classList.add('is-disabled');
						}
					}

					floor.addEventListener('change', updateLiftField);
					updateLiftField();
				});
			</script>
			<?php echo $content_bottom; ?>
		</div>
	</div>
</div>
<?php echo $footer; ?>
