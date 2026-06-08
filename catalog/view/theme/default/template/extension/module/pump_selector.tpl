<?php echo $header; ?>
<div class="container">
  <ul class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
    <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
    <?php } ?>
  </ul>
  <div class="row">
    <?php echo $column_left; ?>
    <?php if ($column_left && $column_right) { ?>
    <?php $class = 'col-sm-6'; ?>
    <?php } elseif ($column_left || $column_right) { ?>
    <?php $class = 'col-sm-9'; ?>
    <?php } else { ?>
    <?php $class = 'col-sm-12'; ?>
    <?php } ?>
    <div id="content" class="<?php echo $class; ?>">
      <?php echo $content_top; ?>
      <h1><?php echo $heading_title; ?></h1>

      <p>Это предварительный подбор. Окончательный выбор насоса требует подтверждения специалистом.</p>

      <?php if ($errors) { ?>
      <div class="alert alert-danger">
        <ul>
          <?php foreach ($errors as $error) { ?>
          <li><?php echo $error; ?></li>
          <?php } ?>
        </ul>
      </div>
      <?php } ?>

      <form action="<?php echo $action; ?>" method="post" class="form-horizontal">
        <fieldset>
          <legend>Данные скважины</legend>

          <div class="form-group">
            <label class="col-sm-3 control-label" for="input-total-well-depth">Глубина скважины, м</label>
            <div class="col-sm-9">
              <input type="text" name="total_well_depth_m" value="<?php echo htmlspecialchars($input['total_well_depth_m'], ENT_QUOTES, 'UTF-8'); ?>" id="input-total-well-depth" class="form-control" />
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-3 control-label">Уровень воды</label>
            <div class="col-sm-9">
              <label class="radio-inline">
                <input type="radio" name="water_level_mode" value="known"<?php if ($input['water_level_mode'] == 'known') { ?> checked="checked"<?php } ?> /> Известен
              </label>
              <label class="radio-inline">
                <input type="radio" name="water_level_mode" value="unknown"<?php if ($input['water_level_mode'] == 'unknown') { ?> checked="checked"<?php } ?> /> Не знаю
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-3 control-label" for="input-water-level">Уровень воды, м</label>
            <div class="col-sm-9">
              <input type="text" name="water_level_m" value="<?php echo htmlspecialchars($input['water_level_m'], ENT_QUOTES, 'UTF-8'); ?>" id="input-water-level" class="form-control" />
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-3 control-label" for="input-distance">Расстояние до дома, м</label>
            <div class="col-sm-9">
              <input type="text" name="distance_to_house_m" value="<?php echo htmlspecialchars($input['distance_to_house_m'], ENT_QUOTES, 'UTF-8'); ?>" id="input-distance" class="form-control" />
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend>Дом и точки водоразбора</legend>

          <div class="form-group">
            <label class="col-sm-3 control-label" for="input-floor">Самая высокая точка</label>
            <div class="col-sm-9">
              <select name="highest_water_point_floor" id="input-floor" class="form-control">
                <option value="1"<?php if ($input['highest_water_point_floor'] == '1') { ?> selected="selected"<?php } ?>>1 этаж</option>
                <option value="2"<?php if ($input['highest_water_point_floor'] == '2') { ?> selected="selected"<?php } ?>>2 этаж</option>
                <option value="3"<?php if ($input['highest_water_point_floor'] == '3') { ?> selected="selected"<?php } ?>>3 этаж</option>
                <option value="custom"<?php if ($input['highest_water_point_floor'] == 'custom') { ?> selected="selected"<?php } ?>>Другое значение</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-3 control-label" for="input-custom-lift">Высота, м</label>
            <div class="col-sm-9">
              <input type="text" name="custom_vertical_lift_m" value="<?php echo htmlspecialchars($input['custom_vertical_lift_m'], ENT_QUOTES, 'UTF-8'); ?>" id="input-custom-lift" class="form-control" />
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-3 control-label">Точки расхода воды</label>
            <div class="col-sm-9">
              <?php foreach ($water_point_labels as $key => $label) { ?>
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="water_points[]" value="<?php echo $key; ?>"<?php if (is_array($input['water_points']) && in_array($key, $input['water_points'])) { ?> checked="checked"<?php } ?> /> <?php echo $label; ?>
                </label>
              </div>
              <?php } ?>
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend>Совместимость</legend>

          <div class="form-group">
            <label class="col-sm-3 control-label">Диаметр обсадной трубы</label>
            <div class="col-sm-9">
              <label class="radio-inline">
                <input type="radio" name="casing_diameter_mode" value="known"<?php if ($input['casing_diameter_mode'] == 'known') { ?> checked="checked"<?php } ?> /> Известен
              </label>
              <label class="radio-inline">
                <input type="radio" name="casing_diameter_mode" value="unknown"<?php if ($input['casing_diameter_mode'] == 'unknown') { ?> checked="checked"<?php } ?> /> Не знаю
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-3 control-label" for="input-casing-diameter">Диаметр, мм</label>
            <div class="col-sm-9">
              <input type="text" name="casing_diameter_mm" value="<?php echo htmlspecialchars($input['casing_diameter_mm'], ENT_QUOTES, 'UTF-8'); ?>" id="input-casing-diameter" class="form-control" />
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-3 control-label">Напряжение</label>
            <div class="col-sm-9">
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
          </div>
        </fieldset>

        <div class="buttons">
          <div class="pull-right">
            <button type="submit" class="btn btn-primary">Подобрать насос</button>
          </div>
        </div>
      </form>

      <?php if ($requirements) { ?>
      <hr />
      <h2>Результат предварительного подбора</h2>

      <?php if ($warnings) { ?>
      <div class="alert alert-warning">
        <ul>
          <?php foreach ($warnings as $warning) { ?>
          <li><?php echo $warning; ?></li>
          <?php } ?>
        </ul>
      </div>
      <?php } ?>

      <h3>Расчетные требования</h3>
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
            <td><?php echo $requirements['selected_voltage']; ?>В<?php if ($requirements['voltage_was_assumed']) { ?>, принято по умолчанию<?php } ?></td>
          </tr>
          <tr>
            <td>Диаметр обсадной трубы</td>
            <td><?php echo ($requirements['casing_diameter_mm'] !== null) ? $requirements['casing_diameter_mm'] . ' мм' : 'не указан'; ?></td>
          </tr>
        </tbody>
      </table>

      <?php if ($products) { ?>
      <h3>Подходящие варианты</h3>
      <div class="row">
        <?php foreach ($products as $product) { ?>
        <div class="col-sm-4">
          <div class="panel panel-default">
            <div class="panel-heading">
              <?php foreach ($product['result_types'] as $result_type) { ?>
              <span class="label label-info"><?php echo isset($result_type_labels[$result_type]) ? $result_type_labels[$result_type] : $result_type; ?></span>
              <?php } ?>
            </div>
            <div class="panel-body">
              <h4><?php echo $product['name'] ? $product['name'] : 'Товар #' . $product['product_id']; ?></h4>
              <p>ID товара: <?php echo $product['product_id']; ?></p>
              <p>Напор: <?php echo $product['max_head_m']; ?> м</p>
              <p>Подача: <?php echo $product['max_flow_l_min']; ?> л/мин</p>
              <p>Диаметр насоса: <?php echo $product['pump_diameter_mm']; ?> мм</p>
              <p>Напряжение: <?php echo $product['voltage']; ?>В</p>
              <p>Запас напора: <?php echo $product['head_reserve']; ?> м</p>
              <p>Запас подачи: <?php echo $product['flow_reserve']; ?> л/мин</p>
              <p>Цена: <?php echo $product['price']; ?></p>
            </div>
          </div>
        </div>
        <?php } ?>
      </div>
      <?php } else { ?>
      <div class="alert alert-info">По введенным условиям не найдено подходящих насосов для предварительного подбора. Проверьте введенные данные или обратитесь к специалисту.</div>
      <?php } ?>
      <?php } ?>

      <?php echo $content_bottom; ?>
    </div>
    <?php echo $column_right; ?>
  </div>
</div>
<?php echo $footer; ?>
