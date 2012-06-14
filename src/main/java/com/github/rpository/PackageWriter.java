package com.github.rpository;

import java.io.File;
import java.io.FileOutputStream;
import java.io.FileReader;
import java.io.IOException;
import java.io.OutputStream;

import org.apache.commons.compress.archivers.ArchiveOutputStream;
import org.apache.commons.compress.archivers.ArchiveStreamFactory;
import org.apache.commons.compress.archivers.tar.TarArchiveOutputStream;
import org.apache.commons.compress.compressors.gzip.GzipCompressorOutputStream;
import org.apache.commons.io.IOUtils;

public class PackageWriter {
	private final ArchiveOutputStream s;
	private final String root;
	private final ArchiveEntryFactory aef;

	public PackageWriter(ArchiveOutputStream os, ArchiveEntryFactory aef, PackageDescription desc, String rootDirName)
			throws IOException {
		s = os;
		this.aef = aef;

		File df = desc.toTempFile();

		if (rootDirName.trim().endsWith("/")) {
			root = rootDirName.trim();
		} else {
			root = rootDirName.trim() + "/";
		}

		appendFile(df, rt("DESCRIPTION"));
		df.delete();
	}

	// public void appendMagic(File in, String entryName) throws IOException {
	// final String et = entryName.trim();
	// final String ext = et.substring(t.lastIndexOf("."));
	//
	// if (ext.length() <= 0 || !ext.substring(0, 1).equalsIgnoreCase("r")) {
	// appendFile(in, "inst/rest/" + entryName.trim());
	// } else if (ext.equalsIgnoreCase("rda")) {
	//
	// }
	// }

	public void appendFile(File in, String entryName) throws IOException {
		FileReader fr = new FileReader(in);
		s.putArchiveEntry(aef.newArchiveEntry(in, rt(entryName.trim())));
		IOUtils.copy(fr, s);
		s.closeArchiveEntry();
		fr.close();
	}

	public void appendLegacy(File in, String entryName) throws IOException {
		appendFile(in, "inst/" + entryName.trim());
	}

	// public void appendData(File in, String entryName) throws IOException {
	// appendFile(in, "inst/data/" + entryName.trim());
	// }
	//
	// public void appendSource(File in, String entryName) throws IOException {
	// appendFile(in, "inst/R/" + entryName.trim());
	// }
	//
	// public void appendDocumentation(File in, String entryName) throws IOException {
	// appendFile(in, "inst/man/" + entryName.trim());
	// }

	public void close() throws IOException {
		s.close();
	}

	private String rt(String entryName) {
		entryName = entryName.trim();

		while (entryName.startsWith("/")) {
			/* TODO inefficient, yes, but also shouldn't occur often */
			entryName = entryName.substring(1);
		}

		if (!entryName.startsWith(root)) {
			return root + "/" + entryName;
		}
		return entryName;
	}

	public static void main(String[] argv) throws IOException {
		final OutputStream fos = new FileOutputStream("/Users/arbeit/Downloads/testRpack.tar.gz");
		final ArchiveOutputStream os = new TarArchiveOutputStream(new GzipCompressorOutputStream(fos));
		final PackageDescription pd = new PackageDescription("rpkgtest", "1.0", "GPL (>=3)", "testpackage, dont use.",
				"testpkg", "John Doe", "Jane Doe");

		final PackageWriter pw = new PackageWriter(os, new ArchiveEntryFactory(ArchiveStreamFactory.TAR), pd, "rtest/");
		pw.appendLegacy(new File("/Users/arbeit/stts.txt"), "test.rda");
		pw.close();

	}
}
