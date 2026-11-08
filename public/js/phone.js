

var data = JSON.parse(document.getElementById('phone-data').textContent);
var country = document.getElementById('country');
var currency = document.getElementById('currency');
var brand = document.getElementById('brand');
var model = document.getElementById('model');
var variant = document.getElementById('variant');

function clearSelect(select, placeholder) {
    select.innerHTML = '';
    select.add(new Option(placeholder, ''));
    select.disabled = true;
}

function addChoice(select, label, id) {
    select.add(new Option(label, id));
    select.disabled = false;
}

function hasPrice(variantId) {
    for (var i = 0; i < data.prices.length; i++) {
        if (data.prices[i].country_id == country.value &&
            data.prices[i].variant_id == variantId) {
            return true;
        }
    }
    return false;
}

function modelHasPrice(modelId) {
    for (var i = 0; i < data.variants.length; i++) {
        if (data.variants[i].model_id == modelId && hasPrice(data.variants[i].id)) {
            return true;
        }
    }
    return false;
}

function changeCountry() {
    currency.value = '';
    clearSelect(brand, 'Choose brand');
    clearSelect(model, 'Choose brand first');
    clearSelect(variant, 'Choose model first');

    for (var i = 0; i < data.countries.length; i++) {
        if (data.countries[i].id == country.value) {
            currency.value = data.countries[i].currency_code;
        }
    }
    for (var b = 0; b < data.brands.length; b++) {
        for (var m = 0; m < data.models.length; m++) {
            if (data.models[m].brand_id == data.brands[b].id && modelHasPrice(data.models[m].id)) {
                addChoice(brand, data.brands[b].brand_name, data.brands[b].id);
                break;
            }
        }
    }
}

function changeBrand() {
    clearSelect(model, 'Choose model');
    clearSelect(variant, 'Choose model first');
    for (var i = 0; i < data.models.length; i++) {
        if (data.models[i].brand_id == brand.value && modelHasPrice(data.models[i].id)) {
            addChoice(model, data.models[i].model_name, data.models[i].id);
        }
    }
}

function changeModel() {
    clearSelect(variant, 'Choose RAM / storage');
    for (var i = 0; i < data.variants.length; i++) {
        var phone = data.variants[i];
        if (phone.model_id == model.value && hasPrice(phone.id)) {
            var label = phone.storage_gb + 'GB Storage';
            if (Number(phone.ram_gb) > 0) label = phone.ram_gb + 'GB RAM / ' + label;
            addChoice(variant, label, phone.id);
        }
    }
}

clearSelect(country, 'Choose country');
for (var i = 0; i < data.countries.length; i++) {
    addChoice(country, data.countries[i].country_name, data.countries[i].id);
}
country.addEventListener('change', changeCountry);
brand.addEventListener('change', changeBrand);
model.addEventListener('change', changeModel);
