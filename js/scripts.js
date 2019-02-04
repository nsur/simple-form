jQuery(document).ready(function() {
    const successClass = 'alert-success';
    const errorClass = 'alert-danger';
    const valideteFields = [
        'first_name', 'last_name', 'email'
    ];
    const successMsg = 'Wow! The email was sent.';
    const errorMsg = 'Ups! Something wrong. ';
    const emptyMsg = errorMsg+ ' Please, fill next fields: ';
    const shortMsg = errorMsg+ ' Next values must have at least 3 characters: ';
    const emailMsg = errorMsg+ ' Email, you have typed, is incorrect';
    const redirect = 'https://www.reliablepsd.com';

    jQuery('.contact-form').on('submit', function(e) {
        e.stopPropagation();
        e.preventDefault();
        hideAlert();

        let form = jQuery(this),
            emptyValues = [],
            shortValues = [];

        form.find('[type="text"], [type="email"], textarea').each(function() {
            let item = jQuery(this),
                name = item.attr('name'),
                value = item.val();
            if(valideteFields.indexOf(name) != -1) {
                if(!value) {
                    emptyValues.push(item.attr('placeholder'));
                } else if(name !== 'email' && value.length < 3) {
                    shortValues.push(item.attr('placeholder'));
                }
            }
        });
        if(emptyValues.length) {
             showAlert(getMessage('empty', emptyValues), errorClass);
        } else if(shortValues.length) {
             showAlert(getMessage('short', shortValues), errorClass);
        } else {
            jQuery.post({
                type: 'POST',
                url: 'server.php',
                dataType: 'json',
                data: {
                    form_data: form.serialize()
                },
                success: function(res) {
                    if(res.error) {
                        let message = getServerErrorMessage(res.errors);
                        showAlert(message, errorClass);
                    } else {
                        showAlert(successMsg, successClass);
                        setInterval(function() {
                            window.location.href = redirect;
                        }, 15000);
                    }
                },
				error: function(res) {
					let message = getMessage('message', 'Error '+ res.status+ ': '+ res.statusText);
					showAlert(message, errorClass);
				}
            });
        }
    });
    function showAlert(message, type) {
        let alert = jQuery('.contact-form .alert');
        if(alert.length) {
            alert.removeClass([successClass, errorClass]);
            alert.html(message);
            alert.addClass(type);
            alert.show(300);
        }
    }
    function hideAlert() {
        let alert = jQuery('.contact-form .alert');
        if(alert.length) {
            alert.hide(300);
        }
    }
    function getServerErrorMessage(errors) {
        let message = '';
        for(i in errors) {
            if(errors[i].length) {
                message += getMessage(i, errors[i], true);
                break;
            }
        }
       return message;
    }
    function getMessage(type, valuesArr, getTitle) {
        let message = '';
        if(getTitle && ['email', 'message'].indexOf(type) !== -1) {
            valuesArr = getFieldTitle(valuesArr);
        }
        switch(type) {
            case 'empty':
                message += emptyMsg+ valuesArr.join(', ');
                break;
            case 'short':
                message += shortMsg+ valuesArr.join(', ');
                break;
            case 'email':
                message += emailMsg;
                break;
			case 'message':
				message += valuesArr;
				break;
			default:
				break;
        }
        return message;
    }
    function getFieldTitle(name) {
        let title = name;
        if(typeof name === 'object' && name.length) {
            let valuesArr = [];
            for(let i = 0; i < name.length; i++) {
                valuesArr.push(getFieldTitle(name[i]));
            }
            title = valuesArr;
        } else {
            let field = jQuery('.contact-form').find('[name="'+ name+ '"]');
            if(field.length) {
                title = field.attr('placeholder');
            }
        }
        return title;
    }
});