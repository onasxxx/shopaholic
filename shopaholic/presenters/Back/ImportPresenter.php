<?php
final class Back_ImportPresenter extends Back_BasePresenter
{
    public function renderDefault()
    {
        $this->template->form = $this->getComponent('importForm');
    }

    public function renderManufacturers()
    {
        $this->template->form = $this->getComponent('importManufacturersForm');
    }

    public function createComponent($name)
    {
        switch ($name) {
            case 'importForm':
                $form = new AppForm($this, $name);
                $form->addFile('file', __('File:'))
                    ->addRule(Form::FILLED, __('You have to entry file.'));
                $form->addText('provision', __('Provision (%):'))
                    ->addRule(Form::FILLED, __('You have to entry provison.'))
                    ->addRule(Form::NUMERIC, __('Provison has to be a number.'))
                    ->addRule(Form::RANGE, __('Provision has to be between 0 and 99.'), array(0, 99));
                $form->addCheckbox('update_only', __('only update existing products'));

                // availability_id
                $availabilities = array(0 => __('–––'));
                foreach (mapper::product_availabilities()->findAll() as $_) {
                    $availabilities[$_->getId()] = $_->getName();
                }
                $form->addSelect('availability_id', __('Default availability:'), $availabilities);
                
                $form->addSubmit('ok', '✔ ' . __('Import'));
                $form->setDefaults(array('provision' => 0, 'update_only' => TRUE));
                $form->onSubmit[] = array($this, 'onImportFormSubmit');
            break;

            case 'importManufacturersForm':
                $form = new AppForm($this, $name);
                $form->addFile('file', __('File:'))
                    ->addRule(Form::FILLED, __('You have to entry file.'));
                $form->addSubmit('ok', '✔ ' . __('Import'));
                $form->onSubmit[] = array($this, 'onImportManufacturersFormSubmit');
            break;

            default:
                parent::createComponent($name);
        }
    }

    public function onImportFormSubmit(Form $form)
    {
        if (!$form->isValid()) {
            return ;
        }

        if (!($handle = @fopen('safe://' . $form['file']->getValue()->getTemporaryFile(), 'r'))) {
            $form->addError(__('Cannot read file.'));
            return ;
        }

        adminlog::log(__('Attempt to import products'));

        // provision
        $provision = intval($form['provision']->getValue());

        // read file
        $import = array();
        $codes = array();
        while (($_ = fgetcsv($handle)) !== FALSE) {
            $product = array();
            list($product['code']/*0*/,
                $product['manufacturer']/*1*/,
                $product['name']/*2*/,
                $product['category']/*3*/,
                /*4*/,
                /*5*/,
                $product['price']/*6*/) = $_;
            $product['price'] = intval(round($product['price'] / (100 - $provision) * 100));
            $import[$product['code']] = $product;
            $codes[] = $product['code'];
        }
        fclose($handle);

        $updated = 0;
        // update in db
        foreach (mapper::products()->findByCodes($codes) as $product) {
            $values = array(
                'id' => $product->getId(),
                'price' => $import[$product->getCode()]['price']
            );
            mapper::products()->updateOne($values);
            unset($import[$product->getCode()]);
            $updated++;
        }

        adminlog::log(__('Updated %d products'), $updated);

        // update only?
        if ($form['update_only']->getValue()) {
            adminlog::log(__('Import successful'));
            $this->redirect('this');
            $this->terminate();
            return ;
        }

        // manufacturers & categories
        $manufacturers = array();
        $categories = array();
        foreach ($import as $k => $_) {
            $m_key = String::webalize($_['manufacturer']);
            $manufacturers[$m_key] = $_['manufacturer'];
            $import[$k]['manufacturer'] = $m_key;
            $c_key = String::webalize($_['category']);
            $categories[$c_key] = $_['category'];
            $import[$k]['category'] = $c_key;
        }

        $manufacturers_added = 0;
        foreach ($manufacturers as $nice_name => $name) {
            if (($_ = mapper::manufacturers()->findByNiceName($nice_name)) === NULL) {
                mapper::manufacturers()->insertOne(array(
                    'nice_name' => $nice_name,
                    'name' => $name
                ));
                $manufacturers[$nice_name] = mapper::manufacturers()->findByNiceName($nice_name)->getId();
                $manufacturers_added++;
            } else {
                $manufacturers[$nice_name] = $_->getId();
            }
            $manufacturers[$nice_name] = intval($manufacturers[$nice_name]);
        }

        adminlog::log(__('Added %d new manufacturers'), $manufacturers_added);

        $categories_added = 0;
        foreach ($categories as $nice_name => $name) {
            if (($_ = mapper::categories()->findByNiceName($nice_name)) === NULL) {
                mapper::categories()->addOne(array(
                    'nice_name' => $nice_name,
                    'name' => $name
                ));
                $categories[$nice_name] = mapper::categories()->findByNiceName($nice_name)->getId();
                $categories_added++;
            } else {
                $categories[$nice_name] = $_->getId();
            }
            $categories[$nice_name] = intval($categories[$nice_name]);
        }

        adminlog::log(__('Added %d new categories'), $categories_added);

        // other
        $other = array();
        if ($form['availability_id']->getValue() != 0) {
            $other['availability_id'] = intval($form['availability_id']->getValue());
        }

        $products_added = 0;
        // insert products
        foreach ($import as $_) {
            $_['manufacturer_id'] = $manufacturers[$_['manufacturer']]; unset($_['manufacturer']);
            $_['category_id'] = $categories[$_['category']]; unset($_['category']);
            $_['nice_name'] = String::webalize($_['name']);
            $_ = array_merge($_, $other);
            mapper::products()->insertOne($_);
            $products_added++;
        }

        adminlog::log(__('Added %d new products'), $products_added);

        adminlog::log(__('Import successful'));

        // all done
        $this->redirect('this');
        $this->terminate();
    }

    public function onImportManufacturersFormSubmit(Form $form)
    {
        if (!$form->isValid()) {
            return ;
        }

        // read imported manufacturers
        if (!($handle = @fopen('safe://' . $form['file']->getValue()->getTemporaryFile(), 'r'))) {
            $form->addError(__('Cannot read file.'));
            return ;
        }

        $import = array();
        while (($_ = fgetcsv($handle)) !== FALSE) {
            $manufacturer = array();
            list(/* id */, $manufacturer['name'], $manufacturer['nice_name']) = $_;
            $import[] = $manufacturer;
        }
        fclose($handle);

        adminlog::log(__('About to import manufacturers'));
        $manufacturers_added = 0;

        // import them
        foreach ($import as $manufacturer) {
            if (($_ = mapper::manufacturers()->findByNiceName($manufacturer['nice_name'])) === NULL) {
                mapper::manufacturers()->insertOne($manufacturer);
                $manufacturers_added++;
            }
        }

        adminlog::log(__('Added %d new manufacturers'), $manufacturers_added);
    }
}
