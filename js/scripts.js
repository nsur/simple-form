jQuery(document).ready(function() {
	// Set unchanged values
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
	// Set submit callback to contact form
    jQuery('.contact-form').on('submit', function(e) {
		// Stop default actions
        e.stopPropagation();
        e.preventDefault();
		// Clear alerts
        hideAlert();
		// Define variables
        let form = jQuery(this),
            emptyValues = [],
            shortValues = [];
		// Validate data at form
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
			// If validation fails - show alerts
            showAlert(getMessage('empty', emptyValues), errorClass);
        } else if(shortValues.length) {
			// If validation fails - show alerts
            showAlert(getMessage('short', shortValues), errorClass);
        } else {
			// Make ajax request
            jQuery.post({
                type: 'POST',
                url: 'server.php',
                dataType: 'json',
                data: {
                    form_data: form.serialize()
                },
                success: function(res) {
					// Callback for success request status
                    if(res.error) {
						// If server errors were caught - show appropriate message
                        let message = getServerErrorMessage(res.errors);
                        showAlert(message, errorClass);
                    } else {
						// If no errors - show success message
                        showAlert(successMsg, successClass);
						// Make redirect to site
                        setInterval(function() {
                            window.location.href = redirect;
                        }, 15000);
                    }
                },
				error: function(res) {
					// Callback for error request status
					let message = getMessage('message', 'Error '+ res.status+ ': '+ res.statusText);
					// Show error massage
					showAlert(message, errorClass);
				}
            });
        }
    });
	/*
	*	Show request alert
	*	message: (string) message text
	*	messageClass: (string) class for message tag to set its appearance
	*/
    function showAlert(message, messageClass) {
        let alert = jQuery('.contact-form .alert');
        if(alert.length) {
            alert.removeClass([successClass, errorClass]);
            alert.html(message);
            alert.addClass(messageClass);
            alert.show(300);
        }
    }
	/*
	*	Hide request alert
	*/
    function hideAlert() {
        let alert = jQuery('.contact-form .alert');
        if(alert.length) {
            alert.hide(300);
        }
    }
	/*
	 *	Get message text from server response
	 *	errors:	(array) array where keys - errors types, values - arrays of fields names, which contain errors
	 *	return (string)	message string
	 */
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
	/*
	 *	Prepare message text depending on message type
	 *	type: (string) type of message
	 *	valuesArr: (mixed) array of field names or single field name
	 *	getTitle: (bool) is correct field name should be get or not, optional param
	 *	return (string)	message string
	 */
    function getMessage(type, valuesArr, getTitle) {
        let message = '';
        if(getTitle && ['email', 'message'].indexOf(type) === -1) {
			// Get human readable field name
            valuesArr = getFieldTitle(valuesArr);
        }
		// Create message string depending on its type
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
	/*
	 *	Get human readable field name by field name attribute
	 *	name: (mixed) array of fields names or single field name
	 *	return (mixed) array of correct fields names or single correct field name
	 */
    function getFieldTitle(name) {
        let title = name;
        if(typeof name === 'object' && name.length) {
			// Get correct fields names for fields array
            let valuesArr = [];
            for(let i = 0; i < name.length; i++) {
				// Fill in array with correct field names using recursion
                valuesArr.push(getFieldTitle(name[i]));
            }
            title = valuesArr;
        } else {
			// Get single correct field name
            let field = jQuery('.contact-form').find('[name="'+ name+ '"]');
            if(field.length) {
                title = field.attr('placeholder');
            }
        }
        return title;
    }
});