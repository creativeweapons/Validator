Validator
=========

Simple Validation Class for PHP, could be used to validate fields passed on RESTful service

Example
=======

  /**
   * How to use
	 */
	public function example()
	{

		try {
			// Array whith validations
			$validaciones = array(
				array('text', $this->param('text'), 'date|date[format=d/m/Y]|phone|is[int,bool,null]|min[5]|max[4]|exact[2]|zip|url|ip|ip[v4]|ip[v6]|ip[reject_private]'),
				array('text1', $this->param('text1'), 'required|email[no_hostname_check]|min[4]|max[25]')
			);
			$this->validator->parse($validaciones);

			if($this->validator->run()){
				/*
				 * Instructions to execute
				 */
			}
			else {
				// Show form errors
				var_dump($this->validator->getExceptions());
			}
		}
		catch(Exception $e){
			// Show fatal errors
			var_dump($e->getMessage());
		}
	}