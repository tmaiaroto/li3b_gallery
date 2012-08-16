Li3b Gallery
============

This is a library built for for Lithium Bootstrap that allows users
to create and manage media galleries for an application.

This library comes with a helpful Thumbnail utility class and hooks
it up to be used with a route.

This library also utilizes Agile Uploader in order to resize images
before uploading them. This allows users to simply add files directly
off their digital camera's memory card without worrying about the
filesize. So long as they are not RAW images. This library does not,
yet, allow users to rotate or crop images though.

Galleries can be created and images can be managed within them from
an easy to user interface that includes drag and drop ordering.

Galleries also have RSS feeds available for them.

This library does not make any assumptions for front-end design
and you are free to use whatever visualizations and JavaScript you
wish for front-end gallery display.

### Dependencies & Requirements
To use this library, make sure that you are either working with the 
Lithium Bootstrap example application or that you have the li3b_core
library and all other dependencies setup.

You will need a MongoDB connection configuration named `li3b_mongodb`
to use this library. This is already configured by default if using
the Lithium Bootstrap example application.

### Installation
You can install this library via the package manager that Lithium Bootstrap
comes with. Open up a command prompt and use the command:
```li3 bootstrap install li3b_gallery```

This will automatically add the library for you.

To manually install this library, simply add it like normal, but ensure
that it is using Lithium Bootstrap layout templates (unless you wish to use
a different layout altogether):
```Libraries::add('li3b_gallery', array('useBootstrapLayout' => '1'));```

Also ensure that it is added after li3b_core.