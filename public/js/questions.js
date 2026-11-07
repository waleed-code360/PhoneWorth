// PhoneWorth questionnaire: plain JavaScript only.
// 1. Show optional comments for answers that need details.
// 2. Show one group of questions at a time to make the page easier.
var cards = document.querySelectorAll('.question-card');
for (var i = 0; i < cards.length; i++) {
    var radios = cards[i].querySelectorAll('input[type="radio"]');
    for (var j = 0; j < radios.length; j++) {
        radios[j].addEventListener('change', function () {
            var card = this.closest('.question-card');
            var area = card.querySelector('.note-area');
            var note = area.querySelector('textarea');
            if (this.getAttribute('data-note') === '1') {
                area.hidden = false;
            } else {
                area.hidden = true;
                note.value = '';
            }
        });
    }
}

var steps = document.querySelectorAll('.question-step');
var stepNumber = 0;
var nextButton = document.getElementById('wizard-next');
var previousButton = document.getElementById('wizard-prev');
var submitButton = document.getElementById('wizard-submit');
var wizardStatus = document.getElementById('wizard-status');
var wizardError = document.getElementById('wizard-error');
var questionForm = document.getElementById('questionnaire');

function showStep(number) {
    stepNumber = number;
    for (var i = 0; i < steps.length; i++) {
        steps[i].hidden = (i !== stepNumber);
    }
    var sectionName = steps[stepNumber].querySelector('legend').textContent;
    wizardStatus.textContent = 'Section ' + (stepNumber + 1) + ' of ' + steps.length + ': ' + sectionName;
    previousButton.hidden = (stepNumber === 0);
    nextButton.hidden = (stepNumber === steps.length - 1);
    submitButton.hidden = (stepNumber !== steps.length - 1);
    wizardError.hidden = true;
}

// Find the first unanswered question in the chosen section.
function missingAnswer(section) {
    var questions = section.querySelectorAll('.question-card');
    for (var i = 0; i < questions.length; i++) {
        if (!questions[i].querySelector('input[type="radio"]:checked')) {
            return questions[i];
        }
    }
    return null;
}

function validateStep(number) {
    var missing = missingAnswer(steps[number]);
    if (missing) {
        wizardError.textContent = 'Please select an answer for every question before continuing.';
        wizardError.hidden = false;
        missing.scrollIntoView();
        return false;
    }
    wizardError.hidden = true;
    return true;
}

if (steps.length > 0) {
    nextButton.addEventListener('click', function () {
        if (validateStep(stepNumber) && stepNumber < steps.length - 1) {
            showStep(stepNumber + 1);
            wizardStatus.scrollIntoView({ block: 'start' });
        }
    });
    previousButton.addEventListener('click', function () {
        if (stepNumber > 0) {
            showStep(stepNumber - 1);
            wizardStatus.scrollIntoView({ block: 'start' });
        }
    });
    questionForm.addEventListener('submit', function (event) {
        for (var i = 0; i < steps.length; i++) {
            if (missingAnswer(steps[i])) {
                event.preventDefault();
                showStep(i);
                validateStep(i);
                return;
            }
        }
    });
    showStep(0);
}
