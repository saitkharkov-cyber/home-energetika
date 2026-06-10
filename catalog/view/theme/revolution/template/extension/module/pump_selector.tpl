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
				:root{
				--accent-color: #0157c0;
				--site-color: #008fc3;
				}
				.pump-selector-page {
				/* max-width: 1180px; */
				margin: 0 auto;
				}
				.pump-selector-intro {
				margin-bottom: 18px;
				}
				.pump-selector-form-panel {
				margin-bottom: 28px;
				padding: 15px;
				background: #f6f8fb;
				border-radius: 4px;
				}
				.pump-selector-form-panel.collapsed {
				display: none;
				}
				.pump-selector-summary {
				margin-bottom: 24px;
				padding: 16px 18px;
				background: #f6f8fb;
				border: 1px solid #e5e5e5;
				border-radius: 4px;
				}
				.pump-selector-summary h3 {
				margin-top: 0;
				margin-bottom: 12px;
				}
				.pump-selector-summary-list {
				margin: 0 0 12px 0;
				padding: 0;
				list-style: none;
				color: #555;
				}
				.pump-selector-summary-list li {
				display: inline-block;
				margin: 0 18px 8px 0;
				}
				.pump-selector-page h1{
				font-size: 24px;
				font-weight: 600;
				margin-top: 0;
				}
				.pump-selector-summary-list strong {
				color: #333;
				}
				.pump-selector-form-grid {
				display: flex;
				flex-wrap: nowrap;
				gap: 20px;
				}
				.pump-selector-form-section {
				padding-left: 25px;
				padding-right: 25px;
				border: 1px solid #e7e7e7;
				border-radius: 15px;
				flex: 1 0 32%;
				max-width: 32.5%;
				background: #fff;
				}
				.pump-selector-form-section h3 {
				margin-top: 0;
				margin-bottom: 14px;
				font-size: 18px;
				display: flex;
				align-items: center;
				justify-content: flex-start;
				}
				.pump-selector-form-section h3 .title-text{
				font-size: 17px!important;
				color: #000;
				font-weight: 600;
				}
				.pump-selector-form-section h3 .title-icon{
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
				.pump-selector-form-section select.form-control{					
				border-color: #cfd7e2;
				height: 38px;
				border-radius: 6px;
				}
				.pump-selector-form-section .checkbox,
				.pump-selector-form-section .radio-inline {
				margin-top: 0;
				}
				.pump-selector-actions {
				margin-top: 0;
				padding-top: 14px;
				text-align: right;
				}
				.pump-selector-empty-result {
				margin-top: 20px;
				padding: 20px;
				background: #f7f7f7;
				border: 1px solid #e5e5e5;
				border-radius: 4px;
				color: #555;
				}
				.pump-selector-results {
				margin-top: 20px;
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
				margin: 10px 15px;
				padding: 10px;
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
				font-weight: bold;
				}
				.pump-selector-debug {
				margin-top: 10px;
				color: #777;
				font-size: 12px;
				line-height: 1.5;
				}
				.pump-selector-after-results {
				margin-top: 24px;
				}
				.btn-primary.btn-pump-selector-submit{
				background: #0157c0;
				padding: 10px 25px 10px 45px;
				font-size: 18px;
				border-radius: 6px;
				}
				.btn-primary.btn-pump-selector-submit .fa.fa-search,
				.btn-primary.btn-pump-selector-submit .fa.fa-spinner.fa-spin{
				position: relative;
				left: -15px 
				}
				.pump-selector-form-section input[type="radio"]:checked::after, 
				.pump-selector-form-section .radio-inline input[type="radio"]:checked::after {
				background: var(--accent-color);
				}
				.pump-selector-form-section input[type="radio"]:checked,
				.pump-selector-form-section .radio-inline input[type="radio"]:checked{
				border-color: var(--accent-color);
				}
				.pump-selector-form-section input[type="checkbox"]:checked::after,
				.pump-selector-form-section .checkbox input[type="checkbox"]:checked::after,
				.pump-selector-form-section .checkbox-inline input[type="checkbox"]:checked::after {
				border-right: 2px solid;
				border-top: 2px solid;
				border-color: var(--accent-color);
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
				.pump-selector-empty-result {
				margin-top: 24px;
				padding: 16px 20px;
				background: #fff;
				border: 1px dashed #cfd7e2;
				border-radius: 8px;
				color: #667085;
				}
				
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
				background: var(--site-color, #008fc3);
				color: #fff;
				font-weight: 700;
				line-height: 30px;
				text-align: center;
				}
				
				.pump-selector-scheme-title {
				font-weight: 700;
				color: #111827;
				margin-right: auto;
				}
				
				.pump-selector-scheme-text {
				color: var(--site-color, #008fc3);
				font-weight: 600;
				white-space: nowrap;
				}
				
				.pump-selector-result-info {
	font-size: 14px;
	line-height: 1.5;
}

.pump-selector-result-info h3 {
	font-size: 19px;
	font-weight: 700;
}

.pump-selector-result-info-item p {
	font-size: 14px;
	line-height: 1.5;
	color: #344054;
	font-weight: 500;
}

.pump-selector-result-info-note {
	font-size: 13px;
	line-height: 1.45;
}

.pump-selector-result-info-note strong,
.pump-selector-result-info-note:before {
	font-weight: 700;
}
.pump-selector-result-info-item .label {
	font-size: 12px;
	font-weight: 700;
	padding: 5px 9px;
}
				
				.pump-selector-scheme-body {
				padding: 0 18px 18px;
				}
				
				.pump-selector-scheme-image {
				display: block;
				max-width: 100%;
				height: auto;
				border-radius: 6px;
				border: 1px solid #e1e7ef;
				background: #fff;
				}
				
				@media (max-width: 767px) {
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
				.pump-selector-scheme-close-wrap {
				margin-top: 12px;
				text-align: right;
				}
				
				.pump-selector-scheme-close {
				border: 1px solid #cfd7e2;
				background: #fff;
				color: var(--accent-color, #0157c0);
				border-radius: 4px;
				padding: 7px 14px;
				font-weight: 600;
				cursor: pointer;
				}
				
				.pump-selector-scheme-close:hover {
				background: #f1f6fd;
				}
				.pump-selector-submit.is-loading {
				opacity: 0.85;
				cursor: wait;
				}
				
				.pump-selector-submit[disabled] {
				pointer-events: none;
				}
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
				.pump-selector-summary-edit{
				background: var(--site-color, #008fc3);
				color: #fff;
				}
				.pump-selector-summary-edit,
				#pump-selector-toggle-form {
				margin-top: 10px;
				padding: 7px 14px;
				border: 1px solid #cfd7e2;
				background: #fff;
				color: #0157c0;
				font-weight: 600;
				border-radius: 4px;
				}
				.pump-selector-js-errors ul {
				margin: 0;
				padding-left: 18px;
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
				.pump-selector-result-info {
				position: sticky;
				top: 15px;
				padding: 18px 16px;
				background: #f8fbff;
				border: 1px solid #dce6f2;
				border-radius: 8px;
				box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
				font-size: 13px;
				line-height: 1.45;
				}
				
				.pump-selector-result-info h3 {
				margin: 0 0 14px;
				padding-bottom: 10px;
				border-bottom: 1px solid #e1e7ef;
				font-size: 18px;
				font-weight: 700;
				color: #111827;
				}
				
				.pump-selector-result-info-item {
				position: relative;
				margin-bottom: 16px;
				padding-left: 0;
				}
				
				.pump-selector-result-info-item .label {
				display: inline-block;
				margin-bottom: 7px;
				padding: 5px 8px;
				font-size: 12px;
				border-radius: 4px;
				}
				
				.pump-selector-result-info-item p {
				margin: 0;
				color: #475467;
				}
				
				.pump-selector-result-info-note {
				margin-top: 16px;
				padding: 12px;
				background: #fff;
				border: 1px solid #e1e7ef;
				border-radius: 6px;
				color: #667085;
				font-size: 12px;
				line-height: 1.45;
				}
				
				.pump-selector-result-info-note:before {
				content: "Важно";
				display: block;
				margin-bottom: 4px;
				font-weight: 700;
				color: #111827;
				}
				.pump-selector-results-layout {
				position: relative;
				margin-top: 18px;
				}
				
				/* левая колонка с пояснением */
				.pump-selector-results-layout > .col-lg-3 {
				padding-right: 18px;
				}
				
				/* правая колонка с товарами */
				.pump-selector-results-layout > .col-lg-9 {
				padding-left: 34px;
				border-left: 0;
				}
				
				/* сам сайдбар */
				.pump-selector-result-info {
				position: sticky;
				top: 15px;
				padding: 18px 16px;
				background: #f8fbff;
				border: 1px solid #dce6f2;
				border-radius: 8px;
				box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
				font-size: 13px;
				line-height: 1.45;
				}
				
				/* чтобы товары не прилипали визуально */
				.pump-selector-results-item {
				margin-bottom: 30px;
				}
				.pump-selector-result-info {
				background: #f8fbff;
				border: 1px solid #dce6f2;
				border-radius: 10px;
				box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
				}
				.pump-selector-page .form-control.is-disabled,
				.pump-selector-page input[disabled] {
				background: #f3f4f6;
				color: #98a2b3;
				cursor: not-allowed;
				}
				
				.pump-selector-consultation {
				margin-top: 24px;
				padding: 20px;
				background: #f7f7f7;
				border: 1px solid #e5e5e5;
				border-radius: 4px;
				}
				.pump-selector-consultation h3 {
				margin-top: 0;
				}
				.pump-selector-consultation p {
				margin-bottom: 14px;
				}
				@media (max-width: 991px) {
				.pump-selector-form-section {
				width: 50%;
				}
				.pump-selector-result-info {
	font-size: 14px;
	line-height: 1.45;
}
.pump-selector-result-info {
	font-size: 14px;
	line-height: 1.45;
}

.pump-selector-result-info h3 {
	margin-bottom: 16px;
	font-size: 20px;
	line-height: 1.25;
	font-weight: 700;
}

.pump-selector-result-info-item {
	margin-bottom: 20px;
}

.pump-selector-result-info-item .label {
	margin-bottom: 8px;
	padding: 5px 9px;
	font-size: 12px;
	font-weight: 700;
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
	font-size: 13px;
	line-height: 1.45;
	color: #475467;
}

.pump-selector-result-info h3 {
	margin-bottom: 16px;
	font-size: 20px;
	line-height: 1.25;
	font-weight: 700;
}

.pump-selector-result-info-item {
	margin-bottom: 20px;
}

.pump-selector-result-info-item .label {
	margin-bottom: 8px;
	padding: 5px 9px;
	font-size: 12px;
	font-weight: 700;
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
	font-size: 13px;
	line-height: 1.45;
	color: #475467;
}}.pump-selector-result-info {
	font-size: 14px;
	line-height: 1.45;
}

.pump-selector-result-info h3 {
	margin-bottom: 16px;
	font-size: 20px;
	line-height: 1.25;
	font-weight: 700;
}

.pump-selector-result-info-item {
	margin-bottom: 20px;
}

.pump-selector-result-info-item .label {
	margin-bottom: 8px;
	padding: 5px 9px;
	font-size: 12px;
	font-weight: 700;
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
	font-size: 13px;
	line-height: 1.45;
	color: #475467;
}
				@media (max-width: 767px) {
				.pump-selector-form-section {
				width: 100%;
				}
				.pump-selector-actions {
				text-align: left;
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
								<?php if ($input['highest_water_point_floor'] == 'custom') { ?>
									<?php echo htmlspecialchars($input['custom_vertical_lift_m'], ENT_QUOTES, 'UTF-8'); ?> м
									<?php } else { ?>
									<?php echo htmlspecialchars($input['highest_water_point_floor'], ENT_QUOTES, 'UTF-8'); ?> этаж
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
										<option value="3"<?php if ($input['highest_water_point_floor'] == '3') { ?> selected="selected"<?php } ?>>3 этаж</option>
										<option value="custom"<?php if ($input['highest_water_point_floor'] == 'custom') { ?> selected="selected"<?php } ?>>Другое значение</option>
									</select>
								</div>
								
								<div class="form-group">
									<label class="control-label" for="input-custom-lift">Высота, м</label>
									<input type="text" name="custom_vertical_lift_m" value="<?php echo htmlspecialchars($input['custom_vertical_lift_m'], ENT_QUOTES, 'UTF-8'); ?>" id="input-custom-lift" class="form-control pump-selector-number" />
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
									<i class="fa fa-spinner fa-spin"></i> Подбираем...
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
										<h3>Как читать подбор</h3>
										
										<div class="pump-selector-result-info-item">
	<span class="label label-success">Лучшая цена</span>
	<div class="result-info-title">Минимальная цена</div>
	<p>Подходящий насос без лишнего запаса.</p>
</div>

<div class="pump-selector-result-info-item">
	<span class="label label-warning">Оптимальный выбор</span>
	<div class="result-info-title">Баланс цены и запаса</div>
	<p>Обычно основной вариант для дома.</p>
</div>

<div class="pump-selector-result-info-item">
	<span class="label label-primary">Премиум</span>
	<div class="result-info-title">Больше запас и бренд</div>
	<p>Для надежности, ресурса и спокойного выбора.</p>
</div>
										<div class="pump-selector-result-info-note">
											Подбор предварительный. Перед покупкой лучше подтвердить параметры скважины со специалистом.
										</div>
									</div>
								</div>
								
								<div class="col-lg-9 col-md-9 col-sm-12 col-xs-12">
									<div class="row">
										
										<?php foreach ($products as $product) { ?>
											<div class="product-layout product-grid col-lg-4 col-md-4 col-sm-6 col-xs-12 pump-selector-results-item">
												<div class="product-thumb transition">
													
													<div class="pump-selector-badges">
														<?php foreach ($product['result_types'] as $result_type) { ?>
															<?php if ($result_type == 'best_price') { ?>
																<?php $label_class = 'label-success'; ?>
																<?php } elseif ($result_type == 'optimal_choice') { ?>
																<?php $label_class = 'label-warning'; ?>
																<?php } elseif ($result_type == 'premium') { ?>
																<?php $label_class = 'label-primary'; ?>
																<?php } else { ?>
																<?php $label_class = 'label-info'; ?>
															<?php } ?>
															
															<span class="label <?php echo $label_class; ?>">
																<?php echo isset($result_type_labels[$result_type]) ? $result_type_labels[$result_type] : $result_type; ?>
															</span>
														<?php } ?>
													</div>
													
													<div class="image">
														<a href="<?php echo $product['href']; ?>">
															<img
															src="<?php echo $product['thumb']; ?>"
															alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
															title="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
															class="img-responsive"
															/>
														</a>
													</div>
													
													<div class="pump-selector-specs clearfix">
														<div class="spec-item">
															Напор
															<span class="spec-value">до <?php echo $product['max_head_m']; ?> м</span>
														</div>
														
														<div class="spec-item">
															Подача
															<span class="spec-value"><?php echo $product['max_flow_l_min']; ?> л/мин</span>
														</div>
													</div>
													
													<div class="caption">
														<h4>
															<a href="<?php echo $product['href']; ?>">
																<?php echo $product['name'] ? $product['name'] : 'Товар #' . $product['product_id']; ?>
															</a>
														</h4>
														
														<?php if ($product['manufacturer']) { ?>
															<p>Производитель: <?php echo $product['manufacturer']; ?></p>
														<?php } ?>
														
														<?php if ($product['price']) { ?>
															<p class="price">
																<?php if (!$product['special']) { ?>
																	<?php echo $product['price']; ?>
																	<?php } else { ?>
																	<span class="price-new"><?php echo $product['special']; ?></span>
																	<span class="price-old"><?php echo $product['price']; ?></span>
																<?php } ?>
																
																<?php if ($product['tax']) { ?>
																	<span class="price-tax">с НДС: <?php echo $product['tax']; ?></span>
																<?php } ?>
															</p>
														<?php } ?>
														
														<div class="pump-selector-debug">
															<div>Запас напора: <?php echo $product['head_reserve']; ?> м</div>
															<div>Запас подачи: <?php echo $product['flow_reserve']; ?> л/мин</div>
															<div>Напряжение: <?php echo $product['voltage']; ?>В</div>
															<div>Диаметр насоса: <?php echo $product['pump_diameter_mm']; ?> мм</div>
															
															<?php if ($product['stock_status']) { ?>
																<div>Наличие: <?php echo $product['stock_status']; ?></div>
															<?php } ?>
														</div>
													</div>
													
													<div class="button-group">
														<button type="button" onclick="cart.add('<?php echo $product['product_id']; ?>', '<?php echo $product['minimum']; ?>');">
															<i class="fa fa-shopping-cart"></i>
															<span>Купить</span>
														</button>
													</div>
													
												</div>
											</div>
										<?php } ?>
										
									</div>
								</div>
								
							</div>
						</div>
						
						<div class="pump-selector-consultation">
							<h3>Не уверены в выборе?</h3>
							<p>
								Специалист проверит параметры скважины, уровень воды, диаметр обсадной трубы и условия установки перед покупкой.
							</p>
							<a href="/index.php?route=information/contact" class="btn btn-primary">
								Получить консультацию специалиста
							</a>
						</div>
						
						<?php } else { ?>
						<div class="alert alert-info">
							По введенным условиям не найдено подходящих насосов для предварительного подбора.
							Проверьте введенные данные или обратитесь к специалисту.
						</div>
					<?php } ?>
					
					<div class="pump-selector-after-results">
						<?php if ($warnings) { ?>
							<h3>Важно перед покупкой</h3>
							
							<div class="alert alert-warning">
								<ul>
									<?php foreach ($warnings as $warning) { ?>
										<li><?php echo $warning; ?></li>
									<?php } ?>
								</ul>
							</div>
						<?php } ?>
						
						<h3>Расчетные параметры</h3>
						
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
			<?php echo $content_bottom; ?>
		</div>
	</div>
</div>
<?php echo $footer; ?>
