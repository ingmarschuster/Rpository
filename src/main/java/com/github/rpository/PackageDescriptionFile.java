package com.github.rpository;

import java.io.BufferedReader;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.UnsupportedEncodingException;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.Map;

/*
 * Quoting from "http://cran.r-project.org/doc/manuals/R-exts.html#The-DESCRIPTION-file":
 * 
 * The format is that of a `Debian Control File' (see the help for ‘read.dcf’ and
 * http://www.debian.org/doc/debian-policy/ch-controlfields.html: R does not require
 * encoding in UTF-8). Continuation lines (for example, for descriptions longer than one
 * line) start with a space or tab.
 * 
 * ‘Package’, ‘Version’, ‘License’, ‘Description’, ‘Title’, ‘Author’, and ‘Maintainer’
 * fields are mandatory, all other fields are optional. For R 2.14.0 or later, ‘Author’
 * and ‘Maintainer’ can be auto-generated from ‘Authors@R’, and should be omitted if
 * the latter is provided (and the package depends on R (>= 2.14): see below for details).
 * 
 * For maximal portability, the DESCRIPTION file should be written entirely in ASCII — 
 * if this is not possible it must contain an ‘Encoding’ field (see below).
 */
public class PackageDescriptionFile {

	private final HashMap<String, String> entries = new HashMap<String, String>();
	private String enc = "US-ASCII";
	private String repr = null;

	public PackageDescriptionFile() {

	};

	public PackageDescriptionFile(InputStream is) throws IOException {
		BufferedReader br = new BufferedReader(new InputStreamReader(is, enc));
		String l = null, lastL = null;
		ArrayList<String> lines = new ArrayList<String>();

		while ((l = br.readLine()) != null) {
			if (l.startsWith("Encoding:")) {
				String kv[] = l.split(":", 1);
				kv[0] = kv[0].trim();
				kv[1] = kv[1].trim();
				kv[1] = enc = new String(kv[1].getBytes(), kv[1]);
				kv[0] = reEncode(kv[0]);

				for (int i = 0; i < lines.size(); ++i) {
					lines.set(i, reEncode(lines.get(i)));
				}

				entries.put(kv[0], kv[1]);
				br = new BufferedReader(new InputStreamReader(is, enc));
			} else if (l.startsWith(" ") || l.startsWith("\t")) {
				final int idx = lines.size() - 1;

				lines.set(idx, lines.get(idx) + " " + l.trim());
			} else {
				lines.add(l);
			}
			lastL = l;
		}

		for (String ln : lines) {
			String kv[] = ln.split(":", 2);
			try {
				kv[0] = kv[0].trim();
				kv[1] = kv[1].trim();
				entries.put(kv[0], kv[1]);
			} catch (ArrayIndexOutOfBoundsException abe) {
				System.err.println(ln);
			}
		}
	};

	public String getEncoding() {
		return enc;
	}

	public String get(String fieldName) {
		return entries.get(fieldName);
	}

	public void set(String fieldName, String content) {
		repr = null;
		entries.put(fieldName, content);
	}

	@Override
	public String toString() {
		if (repr == null) {
			StringBuffer sb = new StringBuffer();

			for (Map.Entry<String, String> e : entries.entrySet()) {
				sb.append(e.getKey()).append(": ").append(e.getValue()).append('\n');
			}
			repr = sb.toString();
		}
		return repr;
	}

	private String reEncode(String in) throws UnsupportedEncodingException {
		return new String(in.getBytes(), enc);
	}

	public static void main(String[] argv) throws IOException {
		PackageDescriptionFile df = new PackageDescriptionFile();
		FileOutputStream f = new FileOutputStream(argv[0], false);

		df.set("Package", "foo");
		df.set("Version", "1.0");
		df.set("License", "GPL-3");
		df.set("Description", "This package is useless. Dont install it.");
		df.set("Title", "Foo, a useless package");
		df.set("Author", "John Doe");
		df.set("Maintainer", "Jane Doe");
		f.write(df.toString().getBytes());
		f.close();

		df = new PackageDescriptionFile(new FileInputStream(argv[1]));
		df.set("Package", df.get("Package") + "-");
		f = new FileOutputStream(argv[1], false);
		f.write(df.toString().getBytes());
		f.close();
	}
}
